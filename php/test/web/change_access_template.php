<?php
header('Content-Type: application/json');
$result = array();

// --------------------------------------------------------------------------
// Load the required php files
require_once __DIR__.'/../lib/Tonic/Autoloader.php';
require_once __DIR__.'/../lib/Pimple/Container.php';
require_once __DIR__.'/../conf/site.conf.php';

$filenames = array(
    __DIR__.'/../src/Base/*.php',
    __DIR__.'/../dbo/GroboAuth/*.php',
    __DIR__.'/../dbo/GroboVersion/*.php',
    __DIR__.'/../dbo/WebRiffs/*.php',
    __DIR__.'/../src/GroboAuth/*.php',
    __DIR__.'/../src/GroboVersion/*.php',
    __DIR__.'/../src/WebRiffs/*.php',
    __DIR__.'/../src/restful/*.php',
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

// Load the database
$db = new PDO($siteConfig['db_config']['dsn'],
        $siteConfig['db_config']['username'],
        $siteConfig['db_config']['password']);
// We handle the db errors ourselves to allow for more flexible handling
//$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// ---------------------------------------------------------------------------
// Create the local source, if it doesn't exist
$localSourceId = $siteConfig['sources']['local']['id'];
$dbSourceId = null;
$data = GroboAuth\GaSource::$INSTANCE->readBy_Source_Name($db, 'local');
Base\BaseDataAccess::checkError($data, new \Exception("read sources"));
if (sizeof($data['result']) <= 0) {
    $data = GroboAuth\GaSource::$INSTANCE->create($db, 'local');
    Base\BaseDataAccess::checkError($data, new \Exception("create source"));
    $dbSourceId = $data['result'];
    $result['local_source'] = 'created';
} else {
    $dbSourceId = $data['result'][0]['Ga_Source_Id'];
    $result['local_source'] = 'exists';
}
if ($dbSourceId != $localSourceId) {
    echo '{"message": "config source id '.$localSourceId.
        ' does not match db source id '.$dbSourceId.'"}';
    die;
}

// ---------------------------------------------------------------------------
// Create a default access template

$data = WebRiffs\TemplateFilmBranchAccess::$INSTANCE->readAll($db);
Base\BaseDataAccess::checkError($data, new \Exception("create access ".$access));
foreach ($data['result'] as $row) {
    $data = WebRiffs\TemplateFilmBranchAccess::$INSTANCE->remove($row['Template_Film_Branch_Access_Id']);
}
foreach (WebRiffs\Access::$BRANCH_ACCESS as $access) {
    $level = WebRiffs\Access::$PRIVILEGE_GUEST;
    if ($access == WebRiffs\Access::$FILM_CREATE ||
            $access == WebRiffs\Access::$FILM_MODIFICATION ||
            $access == WebRiffs\Access::$BRANCH_WRITE ||
            $access == WebRiffs\Access::$BRANCH_USER_MAINTENANCE ||
            $access == WebRiffs\Access::$BRANCH_DELETE) {
        $level = WebRiffs\Access::$PRIVILEGE_TRUSTED;
    }
    if ($access == WebRiffs\Access::$BRANCH_TAG ||
            $access == WebRiffs\Access::$QUIP_WRITE ||
            $access == WebRiffs\Access::$QUIP_TAG ||
            $access == WebRiffs\Access::$FILM_BRANCH) {
        $level = WebRiffs\Access::$PRIVILEGE_USER;
    }
    $data = WebRiffs\TemplateFilmBranchAccess::$INSTANCE->create($db,
        WebRiffs\BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME,
        $access, $level);
    Base\BaseDataAccess::checkError($data, new \Exception("create access ".$access));
    $result['access-'.$access] = $level;
}



echo json_encode($result);
?>
