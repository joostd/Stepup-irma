<?php
require_once __DIR__.'/vendor/autoload.php';

function proto() {
    if( array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER))
        return $_SERVER['HTTP_X_FORWARDED_PROTO'];
    if( array_key_exists('HTTPS', $_SERVER))
        return "on" === $_SERVER['HTTPS'] ? "https" : "http";
    return "http";
}

$options = array(
    "debug" => false,
    "default_locale" => 'en',
    "translation"  =>  array(
        "en" => true,
        "nl" => true,
    ),
    'domain' => '', // The domain for this application, used for the 'stepup_locale' cookie
    "loghandler" => new Monolog\Handler\ErrorLogHandler(),
    "trusted_proxies" => array("127.0.0.1"),
    "default_timezone" => "Europe/Amsterdam",
);

// override options locally. TODO merge with config
if( file_exists(dirname(__FILE__) . "/local_options.php") ) {
    include(dirname(__FILE__) . "/local_options.php");
}

function generate_id($length = 4) {
    return base_convert(time(),10,36) . '-' . base_convert(rand(0, pow(36,$length)),10,36);
}
