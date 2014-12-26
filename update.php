<?php

//error_reporting(E_ALL);       /* for development */
@ini_set("display_errors", 0);  /* don't show errors onscreen */

/* Config */
include_once("config.php");
include_once("functions.php");

// Custom error handler that writes to a file
set_error_handler("dyndns_error_handler");

/* Opts */
$pass = isset($_GET['pass']) ? $_GET['pass'] : null;
$domain = isset($_GET['domain']) ? $_GET['domain'] : null;
$ipaddr = isset($_GET['ipaddr']) ? $_GET['ipaddr'] : null;
$ip6addr = isset($_GET['ip6addr']) ? $_GET['ip6addr'] : null;

/* Validation */
if (!validCred($pass)) {
  trigger_error("bad credentials", E_USER_WARNING);
  respond("failed", "bad credentials");
}

if (!isset($ipaddr) && !isset($ip6addr)) {
  trigger_error("no ip(v6) address given", E_USER_WARNING);
  respond("failed", "no ip(v6) address given");
}

if (isset($ipaddr) && !validIP($ipaddr)) {
  trigger_error("not a valid ip address", E_USER_WARNING);
  respond("failed", "not a valid ip address");
}

if (isset($ip6addr) && !validIPv6($ip6addr)) {
  trigger_error("not a valid ipv6 address", E_USER_WARNING);
  respond("failed", "not a valid ipv6 address");
}

if (!validDomain($domain)) {
  trigger_error("not a valid domain", E_USER_WARNING);
  respond("failed", "not a valid domain");
}

if (!configuredDomain($domain)) {
  trigger_error("unsupported domain", E_USER_WARNING);
  respond("failed", "unsupported domain");
}

// Multi-domain
if (defined('DOMAINS')) {
  $domains = unserialize(DOMAINS);
  $subdomain = $domains[$domain];
} else {
  $subdomain = SUBDOMAIN;
}

/*
 * Request: Get all records of the domain
 */
/* Build request */
$xml_get = implode("", file(XML_GET_ZONE));
$doc_get = DOMDocument::loadXML($xml_get);
$doc_get->formatOutput = true;
$doc_get->getElementsByTagName('user')->item(0)->nodeValue = USER;
$doc_get->getElementsByTagName('password')->item(0)->nodeValue = PASSWORD;
$doc_get->getElementsByTagName('context')->item(0)->nodeValue = CONTEXT;
$doc_get->getElementsByTagName('name')->item(0)->nodeValue = $domain;
$doc_get->getElementsByTagName('system_ns')->item(0)->nodeValue = SYSTEM_NS;
// ATTENTION: This dom document contains credentials
//trigger_error($doc_get->saveXML(), E_USER_NOTICE);

/* Send */
trigger_error("get current zone records", E_USER_NOTICE);
$result = requestCurl($doc_get->saveXML());

/* Receive */
$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
trigger_error($doc_result->saveXML(), E_USER_NOTICE);

/* Abort if we cannot get the current zone records */
$xpath = new DOMXPath($doc_result);
$query = "status/status";
$entries = $xpath->query($query);
if ($entries->length > 0) {
  $status = $entries->item(0)->nodeValue;
  if ($status == "error") {
    trigger_error("cannot get current zone records", E_USER_ERROR);
    respond("failed", "cannot get current zone records");
  }
}

/*
 * Request: Set modified zone records
 */
/* Settings */
$file_put = implode("", file(XML_PUT_ZONE));
$doc_put = DOMDocument::loadXML($file_put);
$doc_put->formatOutput = true;
$doc_put->getElementsByTagName('user')->item(0)->nodeValue = USER;
$doc_put->getElementsByTagName('password')->item(0)->nodeValue = PASSWORD;
$doc_put->getElementsByTagName('context')->item(0)->nodeValue = CONTEXT;

/* Zone */
$frag_data = $doc_result->getElementsByTagName('zone')->item(0);
$frag_data->removeChild($frag_data->getElementsByTagName('created')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('changed')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('domainsafe')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('owner')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('updated_by')->item(0));
$frag = $doc_put->importNode($frag_data, TRUE);
$doc_put->getElementsByTagName('task')->item(0)->appendChild($frag);

/* New dynamic DNS IP */
if (isset($ipaddr)) {
  $xpath = new DOMXPath($doc_put);
  $query = "//task/zone/rr[name='" . $subdomain . "' and type='A']/value";
  $entries = $xpath->query($query);
  if ($entries->length != 1) {
    trigger_error("domain has no dyndns A-record for " . $subdomain, E_USER_ERROR);
    respond("failed", "domain has no dyndns A-record for " . $subdomain);

  }
  $entries->item(0)->nodeValue = $ipaddr;
}

/* New dynamic DNS IPv6 */
if (isset($ip6addr)) {
  $xpath = new DOMXPath($doc_put);
  $query = "//task/zone/rr[name='" . $subdomain . "' and type='AAAA']/value";
  $entries = $xpath->query($query);
  if ($entries->length != 1) {
    trigger_error("domain has no dyndns AAAA-record for " . $subdomain, E_USER_ERROR);
    respond("failed", "domain has no dyndns AAAA-record for " . $subdomain);

  }
  $entries->item(0)->nodeValue = $ip6addr;
}

/* Send */
$xml_put = $doc_put->saveXML();
// ATTENTION: This dom document contains credentials
//trigger_error($xml_put, E_USER_NOTICE);
trigger_error("set new zone records", E_USER_NOTICE);
$result = requestCurl($xml_put);

/* Receive */
$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
trigger_error($doc_result->saveXML(), E_USER_NOTICE);

/* Abort if setting the new zone records failed */
$xpath = new DOMXPath($doc_result);
$query = "status/type";
$entries = $xpath->query($query);
if ($entries->length > 0) {
  $status = $entries->item(0)->nodeValue;
  if ($status != "success") {
    trigger_error("cannot set new zone records", E_USER_ERROR);
    respond("failed", "cannot set new zone records");
  }
}

respond("success");

?>
