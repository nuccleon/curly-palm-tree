# Pushover for HTTP

A simple HTTP api for the [Pushover](https://pushover.net) sercvice.
This is to make the service accessible for smaller devices that does not support https.

### Install via composer

Use the [Composer](https://getcomposer.org/) to install.

Add pushover-http to composer.json configuration file.
```
$ composer require nuccleon/pushover-http
```

And update the composer
```
$ composer update
```

## Usage

### Configuration
Provide your own pushover-http-config.php file to preset the http parameters.
```
cat pushover-http-config.template.php > pushover-http-config.php
```

### HTTP API
Pass parameter either with GET or with POST, At least user, token and priority.

NOTE THAT 'echo' COULD only GETted.

See [Pushover-API](https://pushover.net/api) for detailed parameter description.

```PHP
$params = [
   'user'      =>  $mandatory ,// the user/group key (not e-mail address) of your user (or you), viewable
                               // when logged into our dashboard (often referred to as USER_KEY in our
                               // documentation and code examples)
   'token'     =>  $mandatory, // your application's API token
   'message'   =>  $mandatory, // your message
   'priority'  =>  $optional,  // send as -2 to generate no notification/alert, -1 to always send as a quiet
                               // notification, 1 to display as high-priority and bypass the user's quiet hours,
                               // or 2 to also require confirmation from the user
   'device'    =>  $optional,  // your user's device name to send the message directly to that device,
                               // rather than all of the user's devices (multiple devices may be separated by a comma)
   'title'     =>  $optional,  // your message's title, otherwise your app's name is used
   'url'       =>  $optional,  // a supplementary URL to show with your message
   'urlTitle'  =>  $optional,  // a title for your supplementary URL, otherwise just the URL is shown
   'sound'     =>  $optional,  // The name of one of the sounds supported by device clients to override the user's default sound choice
   'html'      =>  $optional,  // To enable HTML formatting. The normal message content in your message parameter will then be displayed as HTML. 
   'date'      =>  $optional,  // a Unix timestamp of your message's date and time to display to the user, rather than the time your message is received by our API
                               // has to be formated as defined in DATE_FORMAT
   'retry'     =>  $optional,  // Specifies how often (in seconds) the Pushover servers will send the same notification to the user (EMERGENCY only, mandatory for EMERGENCY)
   'expire'    =>  $optional,  // The expire parameter specifies how many seconds your notification will continue to be retried (EMERGENCY only, mandatory for EMERGENCY)
   'callback'  =>  $optional,  // The optional callback parameter may be supplied with a publicly-accessible URL that our servers will send a request to when the user has acknowledged your notification.
   'echo'      =>  $optional   // set this to redirect debug logs to the http response (GET only)
];
```

### Basic Example using wget and GET
```
wget "localhost/pushover-http/pushover-http.php/?user=foo&token=bar&priority=&message=baz&title=&url=&urlTitle=&sound=&html=&device=&date="
```

## Degugging
Append ```pushover-http.php?echo``` to the query to redirect the logger output to the http response instead of the logfile
