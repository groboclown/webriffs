<?php
header('Content-Type: application/json');
$result = array();

// --------------------------------------------------------------------------
// Load the required php files
$filenames = array(
    __DIR__.'../lib/Tonic/Autoloader.php',
    __DIR__.'../lib/Pimple/Container.php',
    __DIR__.'../conf/site.conf.php',
    __DIR__.'/../src/Base/*.php',
    __DIR__.'/../dbo/GroboAuth/*.php',
    __DIR__.'/../dbo/GroboVersion/*.php',
    __DIR__.'/../dbo/WebRiffs/*.php',
    __DIR__.'/../src/GroboAuth/*.php',
    __DIR__.'/../src/WebRiffs/*.php',
    __DIR__.'/../src/restful/*.php',
);
foreach ($filenames as $glob) {
    $globs = glob(str_replace('[', '[[]', $glob));
    if ($globs) {
        foreach ($globs as $filename) {
            require_once $filename;
        }
    }
}

// Load the database
$db = new PDO($siteConfig['db_config']['dsn'],
        $siteConfig['db_config']['username'],
        $siteConfig['db_config']['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// ---------------------------------------------------------------------------
// Create the local source, if it doesn't exist
$localSourceId = $siteConfig['sources']['local']['id'];
$dbSourceId = null;
$data = GroboAuth\GaSource::$INSTANCE->readBy_Source_Name($db, 'local');
Base\BaseDataAccess::checkError($data, new \Exception("read sources"));
if (sizeof($data['result'] <= 0)) {
    $data = GroboAuth\GaSource::$INSTANCE->create($db, 'local');
    Base\BaseDataAccess::checkError($data, new \Exception("create source"));
    $dbSourceId = $data['result'];
    $result['local_source'] = 'created';
} else {
    $dbSourceId = $data['result'][0];
    $result['local_source'] = 'exists';
}
if ($dbSourceId != $localSourceId) {
    echo '{"message": "config source id does not match db source id"}';
    die;
}


// ---------------------------------------------------------------------------
// Create admin users
for ($i = 0; $i < 3; ++$i) {
    $userId = WebRiffs\AuthenticationLayer::createUser($db, 'admin'.$i,
        $dbSourceId, 'admin'.$i,
        WebRiffs\AuthenticationLayer::hashPassword('admin'.$i),
        'admin'.$i.'@localhost.com', WebRiffs\Access::$PRIVILEGE_ADMIN);
    $result['create_admin'.$i] = $userId;
}


// ---------------------------------------------------------------------------
// Create normal users




// ---------------------------------------------------------------------------
// Create links


// ---------------------------------------------------------------------------
// Create films + initial branch + initial change



// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------


echo json_encode($result);
?>
