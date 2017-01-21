<?php
require_once 'DebugLogger.php';

class PhpErrorHandler {
   public function __construct ($verbosity, $logfile) {
      $this->errorhandler = set_error_handler(array($this, 'error_handler'));
      $this->logger = new DebugLogger($verbosity, $logfile);
   }
   public function __destruct() {
      set_error_handler($this->errorhandler);
   }  
   public function error_handler($errno, $errstr, $errfile, $errline) {
      if(null != $this->logger) {
         $this->logger->logError($errstr.", ".basename($errfile).":".$errline);
         return true;
      }
      return false;
   }
   private $errorhandler; 
   private $logger;
}
?>
