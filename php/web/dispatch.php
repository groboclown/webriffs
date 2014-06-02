<?php

// Before anything else is done, ensure that the site is setup.
if (is_file(__DIR__.'/admin.php')) {
    error_log("Client used when admin page exists");
    http_response_code(500);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: deny');
    echo '{"message":"Site not setup."}';
    die;
}


header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: deny');
// FIXME look into how to mandate TLS connections.


// load autoloader (delete as appropriate)
require_once '../lib/Tonic/Autoloader.php';
require_once '../lib/Pimple/Container.php';

require_once '../conf/site.conf.php';

$tonicConfig = array(
    // notice no trailing slash
    'baseUri' => '/api',
    'load' => array(
        __DIR__.'/../src/Base/*.php',
        __DIR__.'/../dbo/GroboAuth/*.php',
        __DIR__.'/../dbo/WebRiffs/*.php',
        __DIR__.'/../src/GroboAuth/*.php',
        __DIR__.'/../src/WebRiffs/*.php',
        __DIR__.'/../src/restful/*.php',
    ),
    #'cache' => new Tonic\MetadataCacheFile('/tmp/tonic.cache') // use the metadata cache
    #'cache' => new Tonic\MetadataCacheAPC // use the metadata cache
);

$app = new Tonic\Application($tonicConfig);


# Debug the routing
#echo "\nRedirect URL: ".$_SERVER['REDIRECT_URL'];
#echo "\nScript name: ".$_SERVER['SCRIPT_NAME'];
#echo "\nRequest URI: ".$_SERVER['REQUEST_URI'];
#echo "\nPHP self: ".$_SERVER['PHP_SELF'];
#die;

#echo $app; die;

$request = new Tonic\Request();


try {

    $container = new Pimple\Container();
    $container['db_config'] = array(
        'dsn' => $siteConfig['db_config']['dsn'],
        'username' => $siteConfig['db_config']['username'],
        'password' => $siteConfig['db_config']['password']
    );
    
    $container['sources'] = $siteConfig['sources'];
    $container['dataStore'] = function($c) {
        $conn = new PDO($c['db_config']['dsn'],
            $c['db_config']['username'],
            $c['db_config']['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    };



    // decode JSON data received from HTTP request
    if ($request->contentType == 'application/json' ||
            $request->contentType == 'text/json') {
        $request->data = json_decode($request->data);
    } elseif (! $request->contentType) {
        // no data passed to server
        $request->data = array();
    } else {
        error_log("Invalid content type: " + $request->contentType);
        throw new Tonic\NotAcceptableException();
    }

    $resource = $app->getResource($request);
    $resource->container = $container;
    $response = $resource->exec();

} catch (Tonic\NotFoundException $e) {
    $response = new Tonic\Response(404, array('message' => $e->getMessage()));

} catch (Tonic\UnauthorizedException $e) {
    // Groboclown: Note that we don't want to raise an actual 401 error,
    // because that will trigger a browser to pop up the authentication
    // dialog, when this will most likely occur during a back-end request.
    // Instead, we'll give a pre-conditioned failed response.
    $response = new Tonic\Response(412, array('message' => $e->getMessage()));
    //$response->wwwAuthenticate = 'Basic realm="WebRiffs"';

} catch (Tonic\MethodNotAllowedException $e) {
    error_log("Method not allowed: ".$e->getMessage());
    error_log($e->getTraceAsString());
        
    $response = new Tonic\Response($e->getCode(), array('message' => $e->getMessage()));
    $response->allow = implode(', ', $resource->allowedMethods());

} catch (Base\ValidationException $e) {
    error_log("Validation error: ".$e->getMessage());
    error_log(print_r($e->problems, true));
    error_log($e->getTraceAsString());
    
    $response = new Tonic\Response($e->getCode(), array(
        'message' => $e->getMessage(),
        'problems' => $e->problems));

} catch (Tonic\Exception $e) {
    error_log("Exception: ".$e->getMessage());
    error_log($e->getTraceAsString());
    
    $response = new Tonic\Response($e->getCode(), array('message' => $e->getMessage()));

} catch (Exception $e) {
    
    # Production level code should not report the full error message,
    # but instead log it and give a generic error to the user.
    error_log("Exception: ".$e->getMessage());
    error_log($e->getTraceAsString());
    
    $response = new Tonic\Response(500, array('message' => "server error"));
}

#echo $response;

// encode output after exception handling
//if ($response->contentType == 'application/json') {
$response->contentType = 'application/json';
//var_dump($response->body);
if (! is_string($response->body)) {
    $response->body = json_encode($response->body);
} else {
    error_log("Request generated string body, instead of array: (" +
        $response->body + ")");
}
//}
$response->output();
