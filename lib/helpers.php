<?php
ORM::configure('mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName);
ORM::configure('username', Config::$dbUsername);
ORM::configure('password', Config::$dbPassword);

function view($template, $data=[]) {
  global $templates;
  return $templates->render($template, $data);
}

function session($key) {
  if(array_key_exists($key, $_SESSION))
    return $_SESSION[$key];
  else
    return null;
}

function redis() {
  static $client = false;
  if(!$client)
    $client = new Predis\Client(Config::$redis);
  return $client;
}

function friendly_url($url) {
  return preg_replace(['/https?:\/\//','/\/$/'],'',$url);
}

function q() {
  static $caterpillar = false;
  if(!$caterpillar) {
    $logdir = __DIR__.'/../scripts/logs/';
    $caterpillar = new Caterpillar('ownyourswarm', Config::$beanstalkServer, Config::$beanstalkPort, $logdir);
  }
  return $caterpillar;
}

function build_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function k($a, $k, $default=null) {
  if(is_array($k)) {
    $result = true;
    foreach($k as $key) {
      $result = $result && array_key_exists($key, $a);
    }
    return $result;
  } else {
    if(is_array($a) && array_key_exists($k, $a) && $a[$k])
      return $a[$k];
    elseif(is_object($a) && property_exists($a, $k) && $a->$k)
      return $a->$k;
    else
      return $default;
  }
}

function micropub_post($user, $params) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $user->micropub_endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $user->micropub_access_token,
    'Content-Type: application/json'
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  $response = curl_exec($ch);

  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $header_str = trim(substr($response, 0, $header_size));

  $error = curl_error($ch);
  return [
    'response' => $response,
    'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'headers' => parse_headers($header_str),
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  ];
}

function parse_headers($headers) {
  $retVal = array();
  $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
  foreach($fields as $field) {
    if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      // If there's already a value set for the header name being returned, turn it into an array and add the new value
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      if(isset($retVal[$match[1]])) {
        $retVal[$match[1]][] = trim($match[2]);
      } else {
        $retVal[$match[1]] = [trim($match[2])];
      }
    }
  }
  return $retVal;
}