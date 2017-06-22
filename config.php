<?php

// TODO move to (local_)options.php
$config = array(
    'keyfile' => dirname(__FILE__) . "/key.pem",
    'certfile' => dirname(__FILE__) . "/cert.pem",
    'irma_api_server' => 'https://irma.surfconext.nl',                  // Our IRMA API server
    'irma_web_server' => 'https://privacybydesign.foundation/tomcat/irma_api_server', // Hosts some static files
    'irma_keyfile' => dirname(__FILE__) . "/irma_key.pem",              // To sign our IRMA disclosure request with
    'irma_apiserver_publickey' => dirname(__FILE__) . "/apiserver.pem", // public key of the IRMA API server
    'irma_attribute_id' => "pbdf.pbdf.surfnet.id",                      // the attribute we ask fo
    'irma_attribute_label' => "Surfnet ID",                             // human-readable version of attribute name
    'irma_keyid' => "surfnet_stepup",                                   // our name at the IRMA API server
    'irma_issuer' => "SURFconext 2-Factor"                              // human-readable version
);

$config['sp']['http://' . $_SERVER['HTTP_HOST'] . '/sp/metadata'] = array(
        'acs' =>  'http://' . $_SERVER['HTTP_HOST'] . '/sp/acs',
        'certfile' => dirname(__FILE__) . '/cert.pem',
);

// override config locally
if( file_exists(dirname(__FILE__) . "/local_config.php") ) {
    include(dirname(__FILE__) . "/local_config.php");
}