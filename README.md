# Pushover for HTTP

A simple HTTP api for the [Pushover](https://pushover.net) sercvice.
This is to make the service accessible for smaller devices that does not support https.

## Installation

Use the [Composer](https://getcomposer.org/) to install.

### composer.json

```json
{
	"require": {
		"nuccleon/pushover-http": "*"
	}
}
```

### Running the composer

```
composer install
```

## Usage

### Basic Example using wget and GET

wget "localhost/pushover-http/pushover-http.php/?user=foo&token=bar&priority=&message=baz&title=&url=&urlTitle=&sound=&html=&device=&date="
