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
// Single domain (default), e.g. home.example.com
define('DOMAIN', 'example.com');
define('SUBDOMAIN', 'home');
// Multi-domain, e.g. home.example.com and base.example.org
//define('DOMAINS', serialize(array("example.com" => array("home", "base"))));
// Nameserver (for single- or multi-domain)
define('SYSTEM_NS', 'ns.example.com');

// Credentials to access this service
define('REMOTE_PASS', 'mylongdyndnspassword');

?>
