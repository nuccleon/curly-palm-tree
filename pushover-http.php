<?php
require_once 'vendor/autoload.php';
require_once 'DebugLogger.php';
require_once 'PhpErrorHandler.php';

date_default_timezone_set('UTC');

use LeonardoTeixeira\Pushover\Client;
use LeonardoTeixeira\Pushover\Message;
use LeonardoTeixeira\Pushover\Priority;
use LeonardoTeixeira\Pushover\Sound;
use LeonardoTeixeira\Pushover\Status;
use LeonardoTeixeira\Pushover\Receipt;
use LeonardoTeixeira\Pushover\Exceptions\PushoverException;

const INI_FILE       = 'pushover-http.ini.php';
const CFG_LOGFILE    = 'logfile';
const CFG_VERBOSITY  = 'verbosity';
const CFG_DATEFORMAT = 'dateformat';
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

$logfile    = isset($cfg_generic[CFG_LOGFILE])    && !empty($cfg_generic[CFG_LOGFILE])    ? $cfg_generic[CFG_LOGFILE]    : 'pushover-http.log';
$verbosity  = isset($cfg_generic[CFG_VERBOSITY])  && !empty($cfg_generic[CFG_VERBOSITY])  ? $cfg_generic[CFG_VERBOSITY]  : DebugLogger::DEBUG;
$dateformat = isset($cfg_generic[CFG_DATEFORMAT]) && !empty($cfg_generic[CFG_DATEFORMAT]) ? $cfg_generic[CFG_DATEFORMAT] : 'Y-n-d H:i:s';

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
$httpApi = [
   API_USER      =>  $mandatory ,// the user/group key (not e-mail address) of your user (or you), viewable
                                 // when logged into our dashboard (often referred to as USER_KEY in our
                                 // documentation and code examples)
   API_TOKEN     =>  $mandatory, // your application's API token
   API_MESSAGE   =>  $mandatory, // your message
   API_PRIORITY  =>  $optional,  // send as -2 to generate no notification/alert, -1 to always send as a quiet
                                 // notification, 1 to display as high-priority and bypass the user's quiet hours,
                                 // or 2 to also require confirmation from the user
   API_DEVICE    =>  $optional,  // your user's device name to send the message directly to that device,
                                 // rather than all of the user's devices (multiple devices may be separated by a comma)
   API_TITLE     =>  $optional,  // your message's title, otherwise your app's name is used
   API_URL       =>  $optional,  // a supplementary URL to show with your message
   API_URL_TITLE =>  $optional,  // a title for your supplementary URL, otherwise just the URL is shown
   API_SOUND     =>  $optional,  // The name of one of the sounds supported by device clients to override the user's default sound choice
   API_HTML      =>  $optional,  // To enable HTML formatting. The normal message content in your message parameter will then be displayed as HTML. 
   API_DATETIME  =>  $optional,  // a Unix timestamp of your message's date and time to display to the user, rather than the time your message is received by our API
                                 // has to be formated as defined in $dateformat
   API_RETRY     =>  $optional,  // Specifies how often (in seconds) the Pushover servers will send the same notification to the user (EMERGENCY only, mandatory for EMERGENCY)
   API_EXPIRE    =>  $optional,  // The expire parameter specifies how many seconds your notification will continue to be retried (EMERGENCY only, mandatory for EMERGENCY)
   API_CALLBACK  =>  $optional,  // The optional callback parameter may be supplied with a publicly-accessible URL that our servers will send a request
                                 // to when the user has acknowledged your notification.
   API_ECHO      =>  $optional,  // set this to redirect debug logs to the http response (GET only)
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
    * Sanity check request query. Don't accept unsupported API keys.
    */
   foreach($request as $key => $value) {
      if(!array_key_exists($key, $httpApi))
         throw new Exception("Unsupported parameter '".$key."'");
   }
   /***************************************************************************
    * Preset param with configuration values got from pushover-http.ini.php
    * They will be overwritten with POST/GET values.
    */
   if(!$iniFile) {
      $logger->logDebug("Did not found ".INI_FILE);
   } else if(isset($request[API_CONFIG])) {
      if(empty($request[API_CONFIG] || !array_key_exists($request[API_CONFIG], $iniFile))) {
         throw new Exception("ini file does not contain the requested configuration for ".$request[API_CONFIG]);
      } else {
         foreach($iniFile[$request[API_CONFIG]] as $key => $value) {
            if(!isset($httpApi[$key])) { // check if parameter from config file exists in the API params ...
               throw new Exception("Invalid parameter '".$key."' found in the ini file");
            } else if(!empty($value)) {  // and copy it to the API params if it has also a valid value
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
      $isset = isset($request[$key]) && !empty($request[$key]);
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
   if(!is_null($httpApi[API_DATETIME][API_VALUE])) {
      $httpApi[API_DATETIME][API_VALUE] = DateTime::createFromFormat($dateformat, $httpApi[API_DATETIME][API_VALUE]);
      $errors = DateTime::getLastErrors();
      if($errors['error_count'] != 0)
         $error = reset($errors['errors']);
      else if($errors['warning_count'] != 0)
         $error = reset($errors['warnings']);
      if(isset($error))
         throw new Exception("Optional parameter API_DATETIME has to be '".$dateformat."' formatted (".$error.")");
   }
   if(!is_null($httpApi[API_PRIORITY][API_VALUE])) {
      if(is_null($httpApi[API_RETRY][API_VALUE]))
         throw new Exception("Mandatory parameter API_RETRY is missing");
      if(is_null($httpApi[API_EXPIRE][API_VALUE]))
         throw new Exception("Mandatory parameter API_EXPIRE is missing");
   }
   if(!is_null($httpApi[API_CALLBACK][API_VALUE])) {
      if (!preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$httpApi[API_CALLBACK][API_VALUE]))
         throw new Exception("API_CALLBACK has to be an valid http / https URL");
   }
   
} catch (Exception $e) {
   $logger->logFatal($e);
   // return error text to the requestor and return HTTP 'Bad Request'
   http_response_code(400);
   exit($e->getMessage());
}
try {
   /***************************************************************************
    * Pushover message preparation
    */
   $message = new Message();
   // values are preinitialized with null
   if(!is_null($httpApi[API_MESSAGE][API_VALUE]))
      $message->setMessage($httpApi[API_MESSAGE][API_VALUE]);
   if(!is_null($httpApi[API_TITLE][API_VALUE]))
      $message->setTitle($httpApi[API_TITLE][API_VALUE]);
   if(!is_null($httpApi[API_URL][API_VALUE]))
      $message->setUrl($httpApi[API_URL][API_VALUE]);
   if(!is_null($httpApi[API_URL_TITLE][API_VALUE]))
      $message->setUrlTitle($httpApi[API_URL_TITLE][API_VALUE]);
   if(!is_null($httpApi[API_PRIORITY][API_VALUE]))
      $message->setPriority($httpApi[API_PRIORITY][API_VALUE]);
   if(!is_null($httpApi[API_RETRY][API_VALUE]))
      $message->setRetry($httpApi[API_RETRY][API_VALUE]);
   if(!is_null($httpApi[API_EXPIRE][API_VALUE]))
      $message->setExpire($httpApi[API_EXPIRE][API_VALUE]);
   if(!is_null($httpApi[API_SOUND][API_VALUE]))
      $message->setSound($httpApi[API_SOUND][API_VALUE]);
   if(!is_null($httpApi[API_HTML][API_VALUE]))
      $message->setHtml($httpApi[API_HTML][API_VALUE]);
   if(!is_null($httpApi[API_DATETIME][API_VALUE]))
      $message->setDate($httpApi[API_DATETIME][API_VALUE]);
} catch (Exception $e) {
   $logger->logFatal($e);
   // return error text to the requestor and return HTTP 'Bad Request'
   http_response_code(400);
   exit($e->getMessage());
}
try {
   /***************************************************************************
    * Pushover message sending
    */
   $client = new Client($httpApi[API_USER][API_VALUE], $httpApi[API_TOKEN][API_VALUE]);
   $receipt = $client->push($message, $httpApi[API_DEVICE][API_VALUE]);
   if(!$receipt->hasReceipt()){
      $logger->logDebug("Got no receipt for prio ".$message->getPriority());
      $receipt->setReceipt("000000000000000000000000000000");
   }
   $logger->logDebug("The message has been pushed!");
   
   // HTTP response
   echo $receipt->getReceipt();   
} catch (Exception $e) {
   $logger->logFatal($e);
   // return error text to the requestor and return HTTP 'Internal Server Error'
   http_response_code(500);
   exit($e->getMessage());
}

?>
