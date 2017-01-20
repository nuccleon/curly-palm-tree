<?php
class DebugLogger {
   const DEBUG = 0;
   const WARN  = 1;
   const ERROR = 2;
   const FATAL = 3;

   public function __construct ($level, $logfile) {
      $this->echo = isset($_GET['echo']) ? true : false;
      $this->verbosity = $level;
      if(!$this->echo) {
         $this->fd=fopen($logfile,"a");
         if(!$this->fd) {
            echo "Failed to create log file ".$logfile." Ignore!<br>";
         }
      }
   }
   public function __destruct() {
      if($this->fd && !$this->echo)
         fclose($this->fd);
   }  
   public function logDebug($message){
      $this->log(self::DEBUG, $message);
   }
   public function logWarn($message){
      $this->log(self::WARN, $message);     
   }
   public function logError($message){
      $this->log(self::ERROR, $message);      
   }
   public function logFatal($message){
      $this->log(self::FATAL, $message);
   }
   private function log($level, $message){
      if(!$this->fd && !$this->echo)
         return; // logfile wasn't greated

      if($level >= $this->verbosity) {
         $type = gettype ( $message );
         switch($type) {
            case "object":
               if ($message instanceof Exception) {
                  $this->log($level, "Thrown ".get_class($message).": '".$message->getMessage()."' in ".basename($message->getFile()).":".$message->getLine());  
                  $tok = strtok($message->getTraceAsString(), "\n");
                  while ($tok !== false) {
                     $this->log($level, "Stack trace: $tok");
                     $tok = strtok("\n");
                  }
               } else if($message instanceof DateTime) {
                  $this->log($level, $message->format('Y-n-d H:i:s')); 
               } else {
                  $this->logFatal("Failed to log messages for objects of type '".get_class($message)."'");
               }
               break;
            case "string":           
               list($usec, $sec) = explode(" ", microtime());
               $time = date("Y-m-d H:i:s:",$sec).intval(round($usec*1000));
               switch($level) {
                  case self::DEBUG: $slevel = "[DEBUG]"; break;
                  case self::WARN:  $slevel = "[WARN]"; break;
                  case self::ERROR: $slevel = "[ERROR]"; break;
                  case self::FATAL: $slevel = "[FATAL]"; break;
               }
               $logmsg = $time.' '.$slevel.' '.$message.PHP_EOL;
               if(!$this->echo)
                  fputs($this->fd, $logmsg);
               else
                  echo $logmsg."<br>";
               break;
            default:
               $this->logFatal("Failed to log messages of type '".$type."'");
         }
      }
   }
   private $verbosity; 
   private $fd;
   private $echo;
}
?>
