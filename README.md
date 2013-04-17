php-dyndns
==========

SchlundTech DynDNS backend for PHP.

Installation
------------

### Prerequisites

- Contract with Schlund-Technologies and access to the [XML-Gateway](http://www.schlundtech.com/services/xml-gateway/).
- Webserver with PHP and php-curl.

### Setup

1. Create a subdomain with an A-record in your domain, say `home.example.com`. It should have a low TTL value such that it is not cached (schlundtech only allows >=60).
2. Upload the files to your webserver. The `update.php` script has to be accessible from the web, for example: `dyndns.example.com/update.php`. *HTTPS is highly recommended!*
3. Copy `config.example.php` to `config.php` and adjust the settings.
4. Create the logdir and give the webserver write-access to it.
5. Set up a cron-job, fritz-box, router, ... to do a request every time the ip-address changes. The URL is `http://dyndns.example.com/update.php?pass=<password>&domain=home.example.com&ipaddr=<ipaddr>&ip6addr=<ip6addr>`


Roadmap
-------

- Support multiple dynamic subdomains
