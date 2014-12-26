<?php

// Path to logs (write access needed)
define('LOG', 'logs/dyndns.log');

// Credentials and configuration provided by SchlundTech
define('HOST', 'https://gateway.schlundtech.de');
define('USER', '');
define('PASSWORD', '');
define('CONTEXT', 42);

// Templates to access the gateway
define('XML_GET_ZONE', 'request-get.xml');
define('XML_PUT_ZONE', 'request-put.xml');

// Domain configuration
// Multi-domain
define('DOMAINS', serialize(array("example.com" => "home", "example.org" => "base")));
// Single domain
//define('DOMAIN', 'example.com');
//define('SUBDOMAIN', 'home');
define('SYSTEM_NS', 'ns.example.com');

// Credentials to access this service
define('REMOTE_PASS', 'mylongdyndnspassword');

?>
