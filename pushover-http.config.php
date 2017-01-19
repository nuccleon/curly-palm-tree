<?php
require_once 'vendor/autoload.php';
use LeonardoTeixeira\Pushover\Priority;
use LeonardoTeixeira\Pushover\Sound;

/**
 * Defalt parameters for pushover service
 */
$param_config = array(
    'user'     => 'the user key',
    'token'    => 'your applications API token',
    'priority' => Priority::NORMAL,
    'device'   => '',
    'sound'    => Sound::PUSHOVER,
    'html'     => true,
    )
?>