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
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


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
// Create admin users
for ($i = 0; $i < 3; ++$i) {
    $data = WebRiffs\User::$INSTANCE->readBy_Username($db, 'admin'.$i);
    Base\BaseDataAccess::checkError($data, new \Exception("get user"));
    if (sizeof($data['result']) <= 0) {
        $userId = WebRiffs\AuthenticationLayer::createUser($db, 'admin'.$i,
            $dbSourceId, 'admin'.$i,
            WebRiffs\AuthenticationLayer::hashPassword('admin '.$i),
            'admin'.$i.'@localhost.com', WebRiffs\Access::$PRIVILEGE_ADMIN);
    } else {
        $userId = intval($data['result'][0]['User_Id']);
    }
    $result['create_admin'.$i] = $userId;
}


// ---------------------------------------------------------------------------
// Create normal users
for ($i = 0; $i < 3; ++$i) {
    $data = WebRiffs\User::$INSTANCE->readBy_Username($db, 'user'.$i);
    Base\BaseDataAccess::checkError($data, new \Exception("get user"));
    if (sizeof($data['result']) <= 0) {
        $userId = WebRiffs\AuthenticationLayer::createUser($db, 'user'.$i,
            $dbSourceId, 'user'.$i,
            WebRiffs\AuthenticationLayer::hashPassword('user '.$i),
            'admin'.$i.'@localhost.com', WebRiffs\Access::$PRIVILEGE_USER);
    } else {
        $userId = intval($data['result'][0]['User_Id']);
    }
    $result['create_user'.$i] = $userId;
}


// ---------------------------------------------------------------------------
// Create links
$data = WebRiffs\AdminLayer::getLinkNamed($db, 'wikipedia-en');
if ($data === null) {
    $data = WebRiffs\AdminLayer::createLink($db, 'wikipedia-en',
        'Open Encyclopedia (English)', 'http://en.wikipedia.org/wiki/',
        // Note: this leaves out articles in international characters.
        '^[a-zA-Z0-9\\$_-\\(\\)]+$');
    $result['create_link_wikipedia'] = $data;
}

$data = WebRiffs\AdminLayer::getLinkNamed($db, 'imdb.com');
if ($data === null) {
    $data = WebRiffs\AdminLayer::createLink($db, 'imdb.com',
        'International Movie Database', 'http://imdb.com/title/',
        '^[a-zA-Z0-9]+$');
    $result['create_link_imdb'] = $data;
}

// Youtube?


// ---------------------------------------------------------------------------
// Create films + initial branch + initial change
$data = WebRiffs\User::$INSTANCE->readBy_Username($db, "user0");
Base\BaseDataAccess::checkError($data, new \Exception("find user0"));
$userData = $data['result'][0];
$idList = WebRiffs\FilmLayer::createFilm($db, $userData, "Slacker", 2011);
$result['create-slacker-2011'] = array(
    'film_id' => $idList[1],
    'branch_id' => $idList[2],
    'change_id' => $idList[3]
);

$idList = WebRiffs\FilmLayer::createFilm($db, $userData, "Slacker", 1991);
$result['create-slacker-1991'] = array(
    'film_id' => $idList[1],
    'branch_id' => $idList[2],
    'change_id' => $idList[3]
);



// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------


echo json_encode($result);
?>
