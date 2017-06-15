<?php

require_once __DIR__.'/../../vendor/autoload.php';

include('../../config.php');
include('../../options.php');

# todo i18n options data (eg SP displayname)

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Cookie;
use \Firebase\JWT\JWT;

if( isset($options["default_timezone"]) )
    date_default_timezone_set($options["default_timezone"]);

if( isset($options["trusted_proxies"]) )
    Request::setTrustedProxies($options["trusted_proxies"]);

$app = new Silex\Application();
$app['debug'] = $options['debug'];

$app->register(new Silex\Provider\SessionServiceProvider(), array(
    'session.storage.options' => array(
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ),
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.handler' => $options['loghandler'],
    'monolog.name' => 'authn',
    'monolog.level'   => Monolog\Logger::WARNING,
));

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array($options['default_locale']),  // used when the current locale has no messages set
));

$app['translator']->addLoader('yaml', new YamlFileLoader());
$app['translator']->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
$app['translator']->addResource('yaml', __DIR__.'/locales/nl.yml', 'nl');

$app->before(
    function (Request $request) use ($app) {
        $request->getSession()->start();
        $stepup_locale = $request->cookies->get('stepup_locale');
        switch($stepup_locale) {
            case "en_GB":
                $stepup_locale = "en";
                break;
            case "nl_NL":
                $stepup_locale = "nl";
                break;
            default:
                $stepup_locale = $request->getPreferredLanguage(['en', 'nl']);
        }
        $app['translator']->setLocale($stepup_locale);
    }
);


### Authentication ###

/**
 * TODO: reuse /enrol function, or should these do different things?
 */
$app->get('/login', function (Request $request) use ($app, $options, $config) {
    $here = urlencode($app['request']->getUri()); // Is this always correct?
    $sid = $app['session']->getId();
    $base = $request->getUriForPath('/');

    $return = stripslashes(filter_var($request->get('return'),FILTER_VALIDATE_URL));
    if($return == false || strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("[%s] illegal return URL '%s'", $sid, $return));
        $return = $base;
    }

    $request_data = $app['session']->get('Request');
    $id = $request_data['nameid']; // do we need to log in some specific user?
    if ($id === '') $id = null;

    $app['monolog']->addInfo(sprintf("[%s] Verifying user '%s'", $sid, $id));
    return $app['twig']->render('index.html', array(
        'self' => $base,
        'return_url' => $return,
        'id' => $id,
        'here' => $here,
        'isMobile' => preg_match("/iPhone|Android|iPad|iPod|webOS/", $_SERVER['HTTP_USER_AGENT']),
        'locale' => $app['translator']->getLocale(),
        'locales' => array_keys($options['translation']),
        'jwt' => get_irma_disclosure_jwt($id),
        'irma_api_server' => $config['irma_api_server'],
        'irma_web_server' => $config['irma_web_server'],
    ));
});

$app->get('/', function (Request $request) use ($app) {
    return "n/a";
});

$app->get('/logout', function (Request $request) use ($app) {
    $app['session']->set('authn', null);
    return "You are logged out";
});

### Enrolment ###

$app->get('/enrol', function (Request $request) use ($app, $options, $config) {
    $here = urlencode($app['request']->getUri()); // Is this always correct?
    $sid = $app['session']->getId();
    $base = $request->getUriForPath('/');

    $return = stripslashes(filter_var($request->get('return'), FILTER_VALIDATE_URL));
    if($return == false || strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("illegal return URL '%s'", $return));
        $return = $base;
    }

    $app['monolog']->addInfo(sprintf("[%s] Enrolling new user", $sid));
    return $app['twig']->render('enrol.html', array(
        'self' => $base,
        'return_url' => $return,
        'here' => $here,
        'jwt' => get_irma_disclosure_jwt(),
        'irma_api_server' => $config['irma_api_server'],
        'irma_web_server' => $config['irma_web_server'],
        'locale' => $app['translator']->getLocale(),
        'locales' => array_keys($options['translation']),
    ));
});

$app->post('/verify-attributes', function (Request $request) use ($app, $config) {
    try {
        // Verify and read the API server's JWT containing the IRMA attributes
        $decoded = (array)JWT::decode($request->get('attrs'), get_apiserver_publickey(), array('RS256'));
        $attrs = (array)$decoded['attributes'];
        if ($decoded["status"] !== "VALID") // TODO improve error handling
            throw new Exception("invalid");
    } catch (Exception $exception) {
        // TODO set some error state here?
        return new Response("Failed to verify attributes: " . $exception->getMessage(), 400);
    }

    $sid = $app['session']->getId();
    $uid = $attrs[$config['irma_attribute_id']];
    $app['session']->set('authn', array('username' => $uid));
    $app['monolog']->addInfo(sprintf("[%s] Verified uid '%s' (%s).", $sid, $uid));

    return new Response("OK");
});

function get_apiserver_publickey() {
    global $config;
    $pubkey = openssl_pkey_get_public("file://" . $config['irma_apiserver_publickey']);
    if(!$pubkey)
        throw new Exception("Failed to load API server public key");
    return $pubkey;
}

function get_irma_privatekey() {
    global $config;
    $pk = openssl_pkey_get_private("file://" . $config['irma_keyfile']);
    if ($pk === false)
        throw new Exception("Failed to load signing key");
    return $pk;
}

function get_irma_disclosure_jwt($expected_value = NULL) {
    global $config;
    $arr = $expected_value == NULL ?
          [ $config['irma_attribute_id'] ]
        : [ $config['irma_attribute_id'] => $expected_value ];
    $sprequest = [
        "sub" => "verification_request",
        "iss" => $config['irma_issuer'],
        "iat" => time(),
        "sprequest" => [
            "validity" => 60,
            "request" => [
                "content" => [
                    [
                        "label" => $config['irma_attribute_label'],
                        "attributes" => $arr
                    ]
                ]
            ]
        ]
    ];

    return JWT::encode($sprequest, get_irma_privatekey(), "RS256", $config['irma_keyid']);
}

$set_locale_cookie = function(Request $request, Response $response, Silex\Application $app) use ($options) {
    $locale = $app['session']->get('locale');
    switch($locale) {
        case "en":
            $locale = "en_GB";
            break;
        case "nl":
            $locale = "nl_NL";
            break;
    }
    $domain = $options['domain'];
    $app['monolog']->addInfo(sprintf("set locale to [%s] for domain '%s'", $locale, $domain));
    $cookie = new Cookie("stepup_locale", $locale, 0, '/', $domain);
    $response->headers->setCookie($cookie);
};

### housekeeping
$app->post('/switch-locale', function (Request $request) use ($app, $options) {
    $return = stripslashes(filter_var($request->get('return_url'), FILTER_VALIDATE_URL));
    if(strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("illegal return URL '%s'", $return));
        $return = $request->getBaseUrl();
    }

    $opt = array(
        'options' => array(
            'default' => 'en',
            'regexp' => '/^[a-z]{2}$/',
        ),
    );
    $locale = filter_var($request->get('irma_switch_locale'), FILTER_VALIDATE_REGEXP, $opt);
    if (array_key_exists($locale, $options['translation'])) {
        $app['session']->set('locale', $locale);
    }

    return $app->redirect($return);
})->after($set_locale_cookie);
    
$app->run();
