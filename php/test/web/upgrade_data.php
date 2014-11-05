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
// Load the users, and set "user view" access to be the same as "user mod"
// access, if it's not already set.
// TODO this kind of logic needs to go in the SQL upgrade code.
$resut['user_view'] = array();
$data = WebRiffs\UserAccess::$INSTANCE->readAll($db);
Base\BaseDataAccess::checkError($data, new \Exception("read user access"));
$userAccess = array();
foreach ($data['result'] as $row) {
    if ($row['Access'] == WebRiffs\Access::$ADMIN_USER_MOD) {
        $userId = strval($row['User_Id']);
        if (! array_key_exists($userId, $userAccess)) {
            $userAccess[$userId] = $row['Privilege_Level'];
        }
    }
    if ($row['Access'] == WebRiffs\Access::$ADMIN_USER_VIEW) {
        $userAccess[$userId] = -1;
    }
    
}
foreach ($userAccess as $user => $priv) {
    if ($priv > -1) {
        $d = WebRiffs\UserAccess::$INSTANCE->create($db, intval($user),
                WebRiffs\Access::$ADMIN_USER_VIEW, $priv);
        Base\BaseDataAccess::checkError($data, new \Exception(
            "create user view access for ".$user." as ".$priv));
        $result['user_view'][$user] = $priv;
    }
}

// ---------------------------------------------------------------------------


echo json_encode($result);
?>
