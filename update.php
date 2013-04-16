<?php

// Disable error reporting for production
error_reporting(0);

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
  respond("failed", "bad credentials");
}

if (!validIP($ipaddr)) {
  respond("failed", "not a valid ip address");
}

if (!validDomain($domain)) {
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

// Send
$result = requestCurl($doc_get->saveXML());

// Response
$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
//echo $doc_result->saveXML();

// Abort if we cannot get the current zone records
$xpath = new DOMXPath($doc_result);
$query = "status/status";
$entries = $xpath->query($query);
if ($entries->length > 0) {
  $status = $entries->item(0)->nodeValue;
  if ($status == "error") {
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
  respond("failed", "domain has no dyndns subdomain");

}
$entries->item(0)->nodeValue = $ipaddr;

// Update: Put
$xml_put = $doc_put->saveXML();
//echo $xml_put;
$result = requestCurl($xml_put);

// Response
/*$doc_result = DOMDocument::loadXML($result);
$doc_result->formatOutput = true;
echo $doc_result->saveXML();*/

respond("success");

function requestCurl($data) {
  $ch = curl_init(HOST);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  
  if (!$data = curl_exec($ch)) {
    echo 'Curl execution error.', curl_error($ch) ."\n";
    return FALSE;
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
  return;
}

?>
