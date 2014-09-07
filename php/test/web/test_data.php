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
        '^[a-zA-Z0-9$_\-()]+$');
        #'^[a-zA-Z0-9\$_-\(\)]+$');
    $result['create_link_wikipedia'] = $data;
}

$data = WebRiffs\AdminLayer::getLinkNamed($db, 'imdb.com');
if ($data === null) {
    $data = WebRiffs\AdminLayer::createLink($db, 'imdb.com',
        'International Movie Database', 'http://imdb.com/title/',
        '^[a-zA-Z0-9]+$');
    $result['create_link_imdb'] = $data;
}

$data = WebRiffs\AdminLayer::getLinkNamed($db, 'YouTube');
if ($data === null) {
    $data = WebRiffs\AdminLayer::createLink($db, 'YouTube',
            'Google YouTube', 'https://youtube.com/watch?v=',
            '^[a-zA-Z0-9]+$');
    $result['create_link_youtube'] = $data;
}


// ---------------------------------------------------------------------------
// Create a default access template

foreach (WebRiffs\Access::$BRANCH_ACCESS as $access) {
    $level = WebRiffs\Access::$PRIVILEGE_GUEST;
    if ($access == WebRiffs\Access::$BRANCH_WRITE ||
            $access == WebRiffs\Access::$BRANCH_USER_MAINTENANCE ||
            $access == WebRiffs\Access::$BRANCH_DELETE) {
        $level = WebRiffs\Access::$PRIVILEGE_TRUSTED;
    }
    if ($access == WebRiffs\Access::$BRANCH_TAG ||
            $access == WebRiffs\Access::$QUIP_WRITE ||
            $access == WebRiffs\Access::$QUIP_TAG) {
        $level = WebRiffs\Access::$PRIVILEGE_USER;
    }
    $data = WebRiffs\TemplateFilmBranchAccess::$INSTANCE->create($db,
        WebRiffs\BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME,
        $access, $level);
    Base\BaseDataAccess::checkError($data, new \Exception("create access ".$access));
    $result['access-'.$access] = $level;
}


// ---------------------------------------------------------------------------
// Create films + initial branch + initial change
$data = WebRiffs\User::$INSTANCE->readBy_Username($db, "user0");
Base\BaseDataAccess::checkError($data, new \Exception("find user0"));
$userData = $data['result'][0];
$idList1 = WebRiffs\FilmLayer::createFilm($db, $userData, "Slacker 2011", 2011,
    WebRiffs\BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME);
$result['create-slacker-2011'] = array(
    'film_id' => $idList1[1],
    'branch_id' => $idList1[2],
    'change_id' => $idList1[3]
);

$idList2 = WebRiffs\FilmLayer::createFilm($db, $userData, "Slacker", 1991,
    WebRiffs\BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME);
$result['create-slacker-1991'] = array(
    'film_id' => $idList2[1],
    'branch_id' => $idList2[2],
    'change_id' => $idList2[3]
);
$slacker1991Id = $idList2[1];
$slacker1991BranchId = $idList2[2];
$slacker1991ChangeId = $idList2[3];
WebRiffs\FilmLayer::saveLinkForFilm($db, $slacker1991Id, "imdb.com",
    "tt0102943");
WebRiffs\FilmLayer::saveLinkForFilm($db, $slacker1991Id, "wikipedia-en",
    "Slacker_(film)");
WebRiffs\FilmLayer::saveLinkForFilm($db, $slacker1991Id, "YouTube",
    "jB4xlYKAVCQ");
//sZSkyWDF6UY

// ---------------------------------------------------------------------------
// Create tags for the branch (header updates)
$tags = array('Theatrical Release', 'Direction Notes');
WebRiffs\BranchLayer::updateAlltagsOnBranch($db, $userData['User_Id'],
    $userData['Ga_User_Id'], $slacker1991BranchId, $tags);


// ---------------------------------------------------------------------------
// Create a quip in a branch and submit the change




// ---------------------------------------------------------------------------


echo json_encode($result);
?>
