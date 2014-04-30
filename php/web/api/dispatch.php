<?php

// load the

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

#echo $request; die;

try {

    $resource = $app->getResource($request);

    #echo $resource; die;

    $response = $resource->exec();

} catch (Tonic\NotFoundException $e) {
    $response = new Tonic\Response(404, $e->getMessage());

} catch (Tonic\UnauthorizedException $e) {
    $response = new Tonic\Response(401, $e->getMessage());
    $response->wwwAuthenticate = 'Basic realm="WebRiffs"';

} catch (Tonic\MethodNotAllowedException $e) {
    $response = new Tonic\Response($e->getCode(), $e->getMessage());
    $response->allow = implode(', ', $resource->allowedMethods());

} catch (Tonic\Exception $e) {
    $response = new Tonic\Response($e->getCode(), $e->getMessage());
}

#echo $response;

$response->output();
