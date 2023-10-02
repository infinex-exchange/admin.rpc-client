<?php

use React\Promise;

class Console {
    private $loop;
    private $log;
    private $amqp;
    
    private $stdio;
    
    function __construct($loop, $log, $amqp) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        
        $this -> log -> debug('Initialized console');
    }
    
    public function start() {
        $th = $this;
        
        $this -> stdio = new Clue\React\Stdio\Stdio($this -> loop);
        $this -> stdio -> setPrompt('infinex> ');
        $this -> stdio -> on('data', function($line) use($th) {
            $line = rtrim($line, "\r\n");
            $all = $th -> stdio -> listHistory();
            if ($line !== '' && $line !== end($all))
                $th -> stdio -> addHistory($line);
            
            try {
                $th -> parse($line);
            } catch(\Exception $e) {
                $th -> log -> error($e -> getMessage());
            }
        });
        
        ob_start(
            function ($chunk) use ($th) {
                $th -> stdio -> write($chunk);
                // discard data from normal output handling
                return '';
            },
            1
        );
        
        return Promise\resolve(null);
    }
    
    public function stop() {
        $this -> stdio -> setPrompt('');
        ob_end_flush();
        return Promise\resolve(null);
    }
    
    private function parse($line) {
        $arrowExp = explode('->', $line, 2);
        if(count($arrowExp) != 2)
            throw new \Exception('Syntax error (arrow)');
        
        $module = trim($arrowExp[0]);
        if($module == '')
            throw new \Exception('Syntax error (module)');
        
        $bracketExp = explode('(', $arrowExp[1], 2);
        if(count($bracketExp) != 2)
            throw new \Exception('Syntax error (brackets)');
        
        $method = trim($bracketExp[0]);
        if($method == '')
            throw new \Exception('Syntax error (method)');
        
        $rest = trim($bracketExp[1]);
        if(substr($bracketExp[1], -1) != ')')
            throw new \Exception('Syntax error (brackets)');
        
        $json = rtrim(substr($bracketExp[1], 0, -1));
        $jsonObj = json_decode($json, true);
        if($json != '' && $jsonObj === null)
            throw new \Exception('Syntax error (json)');
        
        $this -> log -> debug("Module: $module");
        $this -> log -> debug("Method: $method");
        $this -> log -> debug('Params: '.json_encode($jsonObj, JSON_UNESCAPED_SLASHES));
        
        $this -> call($module, $method, $jsonObj);
    }
    
    private function call($module, $method, $jsonObj) {
        $th = $this;
        
        $this -> amqp -> call(
            $module,
            $method,
            $jsonObj
        ) -> then(
            function($resp) {
                echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
                     PHP_EOL;
            }
        ) -> catch(
            function($e) use($th) {
                $th -> log -> error((string) $e);
            }
        );
    }
}

?>