<?php

$odir = __DIR__.'/../../../build/exports';

require_once $odir.'/lib/Tonic/Autoloader.php';
require_once $odir.'/lib/Pimple/Container.php';

# We don't load the site config, because that is specific to the installation.
# Tests will avoid using that directly.
#require_once $odir.'/conf/site.conf.php';

$filenames = array(
    $odir.'/src/Base/*.php',
    $odir.'/dbo/GroboAuth/*.php',
    $odir.'/dbo/GroboVersion/*.php',
    $odir.'/dbo/WebRiffs/*.php',
    $odir.'/src/GroboAuth/*.php',
    $odir.'/src/GroboVersion/*.php',
    $odir.'/src/WebRiffs/*.php',
    $odir.'/src/restful/*.php',
);
foreach ($filenames as $glob) {
    $globs = glob(str_replace('[', '[[]', $glob));
    #error_log(print_r($globs, true));
    if ($globs) {
        foreach ($globs as $filename) {
            require_once $filename;
        }
    }
}


?>