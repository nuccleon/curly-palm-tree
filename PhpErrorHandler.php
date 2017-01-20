<?php
require_once 'DebugLogger.php';

class PhpErrorHandler {
   public function __construct ($verbosity, $logfile) {
      $this->logger = new DebugLogger($verbosity, $logfile);
      $this->errorhandler = set_error_handler(array($this, 'error_handler'));
   }
   public function __destruct() {
      set_error_handler($this->errorhandler);
   }  
   public function error_handler($errno, $errstr, $errfile, $errline) {
      $this->logger->logError($errstr.", ".basename($errfile).":".$errline);
   }
   private $errorhandler; 
   private $logger;
}
?>
