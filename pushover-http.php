<?php
require_once 'vendor/autoload.php';
require_once 'DebugLogger.php';
require_once 'PhpErrorHandler.php';

date_default_timezone_set('UTC');

use LeonardoTeixeira\Pushover\Client;
use LeonardoTeixeira\Pushover\Message;
use LeonardoTeixeira\Pushover\Priority;
use LeonardoTeixeira\Pushover\Sound;
use LeonardoTeixeira\Pushover\Receipt;
use LeonardoTeixeira\Pushover\Status;
use LeonardoTeixeira\Pushover\Exceptions\PushoverException;

const INI_FILE       = 'pushover-http.ini.php';
const CFG_LOGFILE    = 'logfile';
const CFG_VERBOSITY  = 'verbosity';
const CFG_DATEFORMAT = 'dateformat';
const JOB_POLL       = 'poll';
const JOB_PUSH       = 'push';
const API_JOB        = 'job';
const API_USER       = 'user';
const API_TOKEN      = 'token';
const API_MESSAGE    = 'message';
const API_PRIORITY   = 'priority';
const API_DEVICE     = 'device';
const API_TITLE      = 'title';
const API_URL        = 'url';
const API_URL_TITLE  = 'urlTitle';
const API_SOUND      = 'sound';
const API_HTML       = 'html';
const API_DATETIME   = 'datetime';
const API_RETRY      = 'retry';
const API_EXPIRE     = 'expire';
const API_CALLBACK   = 'callback';
const API_RECEIPT    = 'receipt';
const API_VALUE      = 'value';
const API_ECHO       = 'echo';
const API_CONFIG     = 'config';
/**
 * Parse gerneric configuration part from the ini file
 */
$iniFile = false;
if(file_exists(INI_FILE))
   $iniFile = parse_ini_file(INI_FILE, true);
$cfg_generic = array();
if($iniFile && array_key_exists('generic', $iniFile))
   $cfg_generic = &$iniFile['generic'];

$logfile    = isset($cfg_generic[CFG_LOGFILE])    && $cfg_generic[CFG_LOGFILE] != ""    ? $cfg_generic[CFG_LOGFILE]    : 'pushover-http.log';
$verbosity  = isset($cfg_generic[CFG_VERBOSITY])  && $cfg_generic[CFG_VERBOSITY] != ""  ? $cfg_generic[CFG_VERBOSITY]  : DebugLogger::DEBUG;
$dateformat = isset($cfg_generic[CFG_DATEFORMAT]) && $cfg_generic[CFG_DATEFORMAT] != "" ? $cfg_generic[CFG_DATEFORMAT] : 'Y-n-d H:i:s';

$errorhandler = new PhpErrorHandler($verbosity, $logfile);
$logger = new DebugLogger($verbosity, $logfile);
$logger->logDebug("Process '".($_SERVER['REQUEST_URI'])."'");

/**
 * HTTP API DESCRIPTION
 *
 * Pass parameter either with GET or with POST
 * At least user, token and priority
 *
 * NOTE THAT API_ECHO COULD only GETted
 *
 * see https://pushover.net/api for detailed parameter description
 */
$mandatory = [ true, API_VALUE => null ]; // Comes either form POST/GET or from pushover-http.config.php. Do not change from null to ""!
$optional = [ false, API_VALUE => null ]; // null variables will not be set in the message object (see pushover message preparation section)
$httpPushApi = [
   API_JOB       =>  $mandatory, // Job selector. 'push' or 'poll' allowed.
   API_USER      =>  $mandatory, // The user/group key (not e-mail address) of your user (or you), viewable
                                 // when logged into our dashboard (often referred to as USER_KEY in our
                                 // documentation and code examples)
   API_TOKEN     =>  $mandatory, // Your application's API token
   API_MESSAGE   =>  $mandatory, // Your message
   API_PRIORITY  =>  $optional,  // Send as -2 to generate no notification/alert, -1 to always send as a quiet
                                 // notification, 1 to display as high-priority and bypass the user's quiet hours,
                                 // or 2 to also require confirmation from the user
   API_DEVICE    =>  $optional,  // Your user's device name to send the message directly to that device,
                                 // rather than all of the user's devices (multiple devices may be separated by a comma)
   API_TITLE     =>  $optional,  // Your message's title, otherwise your app's name is used
   API_URL       =>  $optional,  // A supplementary URL to show with your message
   API_URL_TITLE =>  $optional,  // A title for your supplementary URL, otherwise just the URL is shown
   API_SOUND     =>  $optional,  // The name of one of the sounds supported by device clients to override the user's default sound choice
   API_HTML      =>  $optional,  // To enable HTML formatting. The normal message content in your message parameter will then be displayed as HTML.
   API_DATETIME  =>  $optional,  // A Unix timestamp of your message's date and time to display to the user, rather than the time your message is received by our API
                                 // has to be formated as defined in $dateformat
   API_RETRY     =>  $optional,  // Specifies how often (in seconds) the Pushover servers will send the same notification to the user (EMERGENCY only, mandatory for EMERGENCY)
   API_EXPIRE    =>  $optional,  // The expire parameter specifies how many seconds your notification will continue to be retried (EMERGENCY only, mandatory for EMERGENCY)
   API_CALLBACK  =>  $optional,  // The optional callback parameter may be supplied with a publicly-accessible URL that our servers will send a request
                                 // to when the user has acknowledged your notification.
   API_ECHO      =>  $optional,  // Set this to redirect debug logs to the http response (GET only)
   API_CONFIG    =>  $optional   // The configuration group within the ini-file that should be used
];

$httpPollApi = [
   API_JOB       =>  $mandatory, // Job selector. 'push' or 'poll' allowed.
   API_RECEIPT   =>  $mandatory, // This receipt can be used to periodically poll the receipts API to get the status of your notification
   API_TOKEN     =>  $mandatory, // Your application's API token
   API_ECHO      =>  $optional,  // Set this to redirect debug logs to the http response (GET only)
   API_CONFIG    =>  $optional   // The configuration group within the ini-file that should be used   
];

try {
   /***************************************************************************
    * Select requested method...
    */
   switch($_SERVER['REQUEST_METHOD']) {
      case 'GET': $request = &$_GET; break;
      case 'POST': $request = &$_POST; break;
      default:
         throw new Exception("Unsupported request method '".$_SERVER['REQUEST_METHOD']."'");
   }
   /***************************************************************************
    * Decide if we should push a notification of poll a notification status.
    * Selecht HTTP API
    */
   if(!isset($request[API_JOB]) || $request[API_JOB] == "") {
      throw new Exception("Got no job parameter. Use either '".JOB_POLL."' or '".JOB_PUSH."'!");
   } else if (!in_array($request[API_JOB], [JOB_POLL, JOB_PUSH])) {
      throw new Exception("Job ".$request[API_JOB]." is invalid. Use either '".JOB_POLL."' or '".JOB_PUSH."'!");
   } else {
      if($request[API_JOB] == JOB_POLL) {
         $httpApi = &$httpPollApi;
      } else {
         $httpApi = &$httpPushApi;
      }
      $httpApi[API_JOB][API_VALUE] = $request[API_JOB];
   }
   /***************************************************************************
    * Sanity check request query. Don't accept unsupported API keys.
    */
    foreach($request as $key => $value) {
      if(!array_key_exists($key, $httpApi))
         throw new Exception("Unsupported parameter '".$key."'");
   }
   /***************************************************************************
    * Preset httpApi with configuration values got from pushover-http.ini.php
    * They will be overwritten with POST/GET values.
    */
   if(!$iniFile) {
      $logger->logDebug("Did not found ".INI_FILE);
   } else if(isset($request[API_CONFIG])) {
      if($request[API_CONFIG] == "" || !array_key_exists($request[API_CONFIG], $iniFile)) {
         throw new Exception("INI file does not contain a valid configuration");
      } else {
         foreach($iniFile[$request[API_CONFIG]] as $key => $value) {
            if(isset($httpApi[$key]) && $value != "") {
               $httpApi[$key][API_VALUE] = $value;
            }
         }
      }
   }
   /***************************************************************************
    * Check if all mandatory parameters are passed to the script.
    * copy parameters from the request query to the internal data
    * represenation ($httpApi) that will be used for the push message.
    */
    foreach($httpApi as $key => &$value) {
      $mandatory = $value[0];
      $isset = isset($request[$key]) && ($request[$key] != "");
      $configured = !is_null($value[API_VALUE]);
      if(!$isset && !$configured) {
         if($mandatory) {
            // throw if the mandatory API key is not part of the request and was not set with the configuration
            $reason = isset($request[$key]) ? 'empty' : 'missing';
            throw new Exception("Mandatory parameter '".$key."' is ".$reason);
         }
      } else {
         $method = INI_FILE;
         $requirement = $mandatory ? 'mandatory' : 'optional';
         if($isset) {
            $value[API_VALUE] = $request[$key];
            $method = $_SERVER['REQUEST_METHOD'];
         }
         $logger->logDebug("Got ".$requirement." '".$key."=".$value[API_VALUE]."' via '".$method."'");
      }
   }
   /***************************************************************************
    * Validate parameters
    * - API_DATETIME has to be formated as defined in $dateformat, e.g. Y-n-d H:i:s
    * - API_CALLBACK has to be an valid http/https URL
    * - API_RETRY and expire becomes mandatory in case of 'priority is EMERGENCY (2)
    */
   if(!is_null($httpPushApi[API_DATETIME][API_VALUE])) {
      $httpPushApi[API_DATETIME][API_VALUE] = DateTime::createFromFormat($dateformat, $httpPushApi[API_DATETIME][API_VALUE]);
      $errors = DateTime::getLastErrors();
      if($errors['error_count'] != 0)
         $error = reset($errors['errors']);
      else if($errors['warning_count'] != 0)
         $error = reset($errors['warnings']);
      if(isset($error))
         throw new Exception("Optional parameter ".API_DATETIME." has to be '".$dateformat."' formatted (".$error.")");
   }
   if(!is_null($httpPushApi[API_PRIORITY][API_VALUE]) && $httpPushApi[API_PRIORITY][API_VALUE] == Priority::EMERGENCY) {
      if(is_null($httpPushApi[API_RETRY][API_VALUE]))
         throw new Exception("Mandatory parameter ".API_RETRY." is missing");
      if(is_null($httpPushApi[API_EXPIRE][API_VALUE]))
         throw new Exception("Mandatory parameter ".API_EXPIRE." is missing");
   }
   if(!is_null($httpPushApi[API_CALLBACK][API_VALUE])) {
      if (!preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$httpPushApi[API_CALLBACK][API_VALUE]))
         throw new Exception(API_CALLBACK." has to be an valid http / https URL");
   }
   /***************************************************************************
    * Execute the poll / push job
    */
   if($httpApi[API_JOB][API_VALUE] == JOB_PUSH) {
      /***************************************************************************
       * Message preparation for push
       */
      $message = new Message();
      // values are preinitialized with null
      if(!is_null($httpPushApi[API_MESSAGE][API_VALUE]))
         $message->setMessage($httpPushApi[API_MESSAGE][API_VALUE]);
      if(!is_null($httpPushApi[API_TITLE][API_VALUE]))
         $message->setTitle($httpPushApi[API_TITLE][API_VALUE]);
      if(!is_null($httpPushApi[API_URL][API_VALUE]))
         $message->setUrl($httpPushApi[API_URL][API_VALUE]);
      if(!is_null($httpPushApi[API_URL_TITLE][API_VALUE]))
         $message->setUrlTitle($httpPushApi[API_URL_TITLE][API_VALUE]);
      if(!is_null($httpPushApi[API_PRIORITY][API_VALUE]))
         $message->setPriority($httpPushApi[API_PRIORITY][API_VALUE]);
      if(!is_null($httpPushApi[API_RETRY][API_VALUE]))
         $message->setRetry($httpPushApi[API_RETRY][API_VALUE]);
      if(!is_null($httpPushApi[API_EXPIRE][API_VALUE]))
         $message->setExpire($httpPushApi[API_EXPIRE][API_VALUE]);
      if(!is_null($httpPushApi[API_SOUND][API_VALUE]))
         $message->setSound($httpPushApi[API_SOUND][API_VALUE]);
      if(!is_null($httpPushApi[API_HTML][API_VALUE]))
         $message->setHtml($httpPushApi[API_HTML][API_VALUE]);
      if(!is_null($httpPushApi[API_DATETIME][API_VALUE]))
         $message->setDate($httpPushApi[API_DATETIME][API_VALUE]);      
      /***************************************************************************
       * Pushover notification push
       */
      $client = new Client($httpApi[API_USER][API_VALUE], $httpApi[API_TOKEN][API_VALUE]);
      $receipt = $client->push($message, $httpApi[API_DEVICE][API_VALUE]);
      if(!$receipt->hasReceipt()){
         $logger->logDebug("Got no receipt for prio ".$message->getPriority());
         $receipt->setReceipt("000000000000000000000000000000");
      }
      $logger->logDebug("The message has been pushed!");
      /***************************************************************************
       * http response
       */    
      echo $receipt->getReceipt();
   } else if($httpApi[API_JOB][API_VALUE] == JOB_POLL) {    
      /***************************************************************************
       * Message preparation for push
       */       
      $receipt = new Receipt($httpPollApi[API_RECEIPT][API_VALUE]);       
      /***************************************************************************
       * Pushover notification status polling
       */       
      $client = new Client(null, $httpApi[API_TOKEN][API_VALUE]);
      $status = $client->poll($receipt);
      $logger->logDebug("Receipt ".$httpApi[API_RECEIPT][API_VALUE]." has been polled!");
      /***************************************************************************
       * http response
       */    
      echo Status::STATUS.": ".$status->getStatus()."<br>";     
      echo Status::ACKNOWLEDGED.": ".$status->getAcknowledged()."<br>";
      echo Status::ACKNOWLEDGED_AT.": ".$status->getAcknowledgedAt()."<br>";
      echo Status::ACKNOWLEDGED_BY.": ".$status->getAcknowledgedBy()."<br>";
      echo Status::ACKNOWLEDGED_BY_DEVICE.": ".$status->getAcknowledgedByDevice()."<br>";
      echo Status::LAST_DELIVERED_AT.": ".$status->getLastDeliveredAt()."<br>";
      echo Status::EXPIRED.": ".$status->getExpired()."<br>";
      echo Status::EXPIRED_AT.": ".$status->getExpiredAt()."<br>";     
      echo Status::CALLED_BACK.": ".$status->getCalledBack()."<br>";     
      echo Status::CALLED_BACK_AT.": ".$status->getCalledBackAt();     
   }
   else {
      throw new Exception("Unexpected ".API_JOB." value ".$httpApi[API_JOB][API_VALUE]);
   }
} catch (Exception $e) {
   $logger->logFatal($e);
   // return error text to the requestor and return HTTP 'Internal Server Error'
   http_response_code(400);
   exit($e->getMessage());
}

?>
