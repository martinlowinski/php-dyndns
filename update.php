<?php

//error_reporting(E_ALL);       /* for development */
@ini_set("display_errors", 0);  /* don't show errors onscreen */

// Custom error handler that writes to a file
set_error_handler("dyndns_error_handler");

// Config
include_once("config.php");

// Opts
if ($_GET) {
  $pass = $_GET['pass'];
  $domain = $_GET['domain'];
  $ipaddr = $_GET['ipaddr'];
} else {
  $shortopts = "p:d:i:";
  $opts = getopt($shortopts);
  $pass = isset($opts["p"]) ? $opts["p"] : null;
  $domain = isset($opts["d"]) ? $opts["d"] : null;
  $ipaddr = isset($opts["i"]) ? $opts["i"] : null;
}

// Validation
if (!validCred($pass)) {
  trigger_error("bad credentials", E_USER_WARNING);
  respond("failed", "bad credentials");
}

if (!validIP($ipaddr)) {
  trigger_error("not a valid ip address", E_USER_WARNING);
  respond("failed", "not a valid ip address");
}

if (!validDomain($domain)) {
  trigger_error("not a valid domain", E_USER_WARNING);
  respond("failed", "not a valid domain");
}

// Extract subdomain
$subdomain = array_shift(explode(".", $domain));

// Request
$xml_get = implode("", file(XML_GET_ZONE));
$doc_get = DOMDocument::loadXML($xml_get);
$doc_get->formatOutput = true;
$doc_get->getElementsByTagName('user')->item(0)->nodeValue = USER;
$doc_get->getElementsByTagName('password')->item(0)->nodeValue = PASSWORD;
$doc_get->getElementsByTagName('context')->item(0)->nodeValue = CONTEXT;
$doc_get->getElementsByTagName('name')->item(0)->nodeValue = DOMAIN;
// ATTENTION: This dom document contains credentials
//trigger_error($doc_get->saveXML(), E_USER_NOTICE);

// Send
trigger_error("get current zone records", E_USER_NOTICE);
$result = requestCurl($doc_get->saveXML());

// Response
$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
trigger_error($doc_result->saveXML(), E_USER_NOTICE);

// Abort if we cannot get the current zone records
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

// Update: Settings
$file_put = implode("", file(XML_PUT_ZONE));
$doc_put = DOMDocument::loadXML($file_put);
$doc_put->formatOutput = true;
$doc_put->getElementsByTagName('user')->item(0)->nodeValue = USER;
$doc_put->getElementsByTagName('password')->item(0)->nodeValue = PASSWORD;
$doc_put->getElementsByTagName('context')->item(0)->nodeValue = CONTEXT;

// Update: Zone
$frag_data = $doc_result->getElementsByTagName('zone')->item(0);
$frag_data->removeChild($frag_data->getElementsByTagName('created')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('changed')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('domainsafe')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('owner')->item(0));
$frag_data->removeChild($frag_data->getElementsByTagName('updated_by')->item(0));
$frag = $doc_put->importNode($frag_data, TRUE);
$doc_put->getElementsByTagName('task')->item(0)->appendChild($frag);

// Update: New IP
$xpath = new DOMXPath($doc_put);
$query = "//task/zone/rr[name='" . $subdomain . "']/value";
$entries = $xpath->query($query);
if ($entries->length != 1) {
  trigger_error("domain has no dyndns subdomain", E_USER_ERROR);
  respond("failed", "domain has no dyndns subdomain");

}
$entries->item(0)->nodeValue = $ipaddr;

// Update: Put
$xml_put = $doc_put->saveXML();
// ATTENTION: This dom document contains credentials
//trigger_error($xml_put, E_USER_NOTICE);
trigger_error("set new zone records", E_USER_NOTICE);
$result = requestCurl($xml_put);

// Response
$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
trigger_error($doc_result->saveXML(), E_USER_NOTICE);

respond("success");

function requestCurl($data) {
  $ch = curl_init(HOST);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  
  if (!$data = curl_exec($ch)) {
    trigger_error('Curl execution error.', curl_error($ch), E_USER_ERROR);
    return false;
  }

  curl_close($ch);
  return $data;
}

function validIP($ip) {
  if (filter_var($ip, FILTER_VALIDATE_IP)) {
    return true;
  }
  return false;
}

function validDomain($domain)
{
  return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain) //valid chars check
    && preg_match("/^.{1,253}$/", $domain) //overall length check
    && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain)); //length of each label
}

function validCred($pass) {
  if ($pass == REMOTE_PASS) {
    return true;
  }
  return false;
}

function respond($status, $msg = "") {
  header('Content-type: application/json');
  $response = array();
  $response["status"] = $status;
  if (!empty($msg)) {
    $response["msg"] = $msg;
  }
  echo json_encode($response);
  exit();
}

function dyndns_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }

    $date = date(DATE_W3C);

    switch ($errno) {
    case E_USER_ERROR:
        $str .= "$date ERROR [$errno]: $errstr, Fatal error on line $errline in file $errfile";
        break;

    case E_USER_WARNING:
        $str .= "$date WARNING [$errno]: $errstr\n";
        break;

    case E_USER_NOTICE:
        $str .= "$date NOTICE [$errno]: $errstr\n";
        break;

    default:
        $str .= "$date Unknown error type: [$errno] $errstr\n";
        break;
    }

    file_put_contents(LOG, $str, FILE_APPEND);

    /* Don't execute PHP internal error handler */
    return true;
}

?>
