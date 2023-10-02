<?php

require __DIR__.'/Console.php';

class App extends Infinex\App\App {
    private $console;
    
    function __construct() {
        parent::__construct('admin.rpc-client');
        
        $this -> console = new Console(
            $this -> loop,
            $this -> log,
            $this -> amqp
        );
    }
    
    public function start() {
        $th = $this;
        
        parent::start() -> then(
            function() use($th) {
                return $th -> console -> start();
            }
        ) -> catch(
            function($e) {
                $th -> log -> error('Failed start app: '.((string) $e));
            }
        );
    }
    
    public function stop() {
        $th = $this;
        
        $this -> console -> stop() -> then(
            function() use($th) {
                $th -> parentStop();
            }
        );
    }
    
    private function parentStop() {
        parent::stop();
    }
}

?>