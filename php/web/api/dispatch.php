<?php

header('Content-Type: text/json');

// load autoloader (delete as appropriate)
require_once '../../lib/Tonic/Autoloader.php';
require_once '../../lib/Pimple/Container.php';

require_once '../../conf/site.conf.php';

$tonicConfig = array(
    'baseUri' => '/api/',
    'load' => array(
        __DIR__.'/../src/WebRiffs/*.php', // load example resources
    ),
    #'cache' => new Tonic\MetadataCacheFile('/tmp/tonic.cache') // use the metadata cache
    #'cache' => new Tonic\MetadataCacheAPC // use the metadata cache
);

$app = new Tonic\Application($tonicConfig);

$container = new Pimple\Container();
$container['db_config'] =& $siteConfig['db_config'];


#echo $app; die;

$request = new Tonic\Request();


try {
    // decode JSON data received from HTTP request
    if ($request->contentType == 'application/json') {
        $request->data = json_decode($request->data);
    }

    $resource = $app->getResource($request);
    $response = $resource->exec();

} catch (Tonic\NotFoundException $e) {
    $response = new Tonic\Response(404, array('message' => $e->getMessage()));

} catch (Tonic\UnauthorizedException $e) {
    $response = new Tonic\Response(401, array('message' => $e->getMessage()));
    $response->wwwAuthenticate = 'Basic realm="WebRiffs"';

} catch (Tonic\MethodNotAllowedException $e) {
    $response = new Tonic\Response($e->getCode(), array('message' => $e->getMessage()));
    $response->allow = implode(', ', $resource->allowedMethods());

} catch (Base\ValidationException $e) {
    $response = new Tonic\Response($e->getCode(), array(
        'message' => $e->getMessage(),
        'problems' => $e->problems));

} catch (Tonic\Exception $e) {
    $response = new Tonic\Response($e->getCode(), array('message' => $e->getMessage()));

} catch (Exception $e) {
    # FIXME Production level code should not report the full error message,
    # but instead log it and give a generic error to the user.
    $response = new Tonic\Response(500, array('message' => $e->getMessage()));
}

#echo $response;

// encode output after exception handling
//if ($response->contentType == 'application/json') {
$response->body = json_encode($response->body);
//}

$response->output();
