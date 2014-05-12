<html>
<head>
    <title>General Test Page</title>
</head>
<body>
    <h1>Tests</h1>
    <p>
    Some general tests of the current API.  These will need to be eventually
    moved into a real test framework.
    </p>

<?php
try{
    require_once '../conf/site.conf.php';
    require_once '../lib/phpass/PasswordHash.php';
    #require_once '../src/Base/all_base.php';
    require_once '../lib/Tonic/Exception.php';
    require_once '../src/Base/DboBase.php';
    require_once '../src/Base/ValidationException.php';
    require_once '../dbo/GroboAuth/GaUser.php';
    require_once '../dbo/GroboAuth/GaSource.php';
    require_once '../dbo/GroboAuth/GaUserSource.php';
    require_once '../dbo/GroboAuth/GaLoginAttempt.php';
    require_once '../dbo/GroboAuth/GaPasswordRequest.php';
    require_once '../dbo/GroboAuth/GaSession.php';
    require_once '../dbo/WebRiffs/User.php';
    require_once '../dbo/WebRiffs/Film.php';
    require_once '../dbo/WebRiffs/FilmVersion.php';
    require_once '../dbo/WebRiffs/FilmVersionTag.php';
    require_once '../dbo/WebRiffs/Quip.php';
    require_once '../dbo/WebRiffs/QuipVersion.php';
    require_once '../dbo/WebRiffs/Tag.php';
    require_once '../dbo/WebRiffs/QuipTag.php';
    require_once '../src/GroboAuth/DataAccess.php';

    $db = new PDO($siteConfig['db_config']['dsn'],
        $siteConfig['db_config']['username'],
        $siteConfig['db_config']['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Only for testing purposes
    $db->prepare('DELETE FROM GA_SESSION')->execute();
    $db->prepare('DELETE FROM GA_LOGIN_ATTEMPT')->execute();
    $db->prepare('DELETE FROM GA_PASSWORD_REQUEST')->execute();
    $db->prepare('DELETE FROM GA_USER_SOURCE')->execute();
    $db->prepare('DELETE FROM GA_USER')->execute();
    $db->prepare('DELETE FROM GA_SOURCE')->execute();
    
    $sources = array();
    $sources[] = GroboAuth\DataAccess::createSource($db, "Source1");
    $sources[] = GroboAuth\DataAccess::createSource($db, "Source2");
    
    $users = array();
    $userSources = array();
    for ($i = 0; $i < 10; $i++) {
        $id = GroboAuth\DataAccess::createUser($db);
        $users[] = $id;
        echo "\n<br>Setting user source for ".$id;
        $userSources[] = GroboAuth\DataAccess::setUserSource($db, $id, $sources[0], "User0_".$i, '1234');
        $userSources[] = GroboAuth\DataAccess::setUserSource($db, $id, $sources[1], "User1_".$i, '4321');
    }
    $deletedUser = $users[9];
    GroboAuth\DataAccess::removeUser($db, $users[9]);
    unset($users[9]);
    unset($userSources[19]);
    unset($userSources[18]);
    
    // update password
    $userSources[] = GroboAuth\DataAccess::setUserSource($db, $users[2], $sources[0], "User0_2", '12234');
    
    echo "\n<br>User Source count: ".GroboAuth\DataAccess::countUserSources($db);
    
    $data = GroboAuth\DataAccess::getUserSource($db, $deletedUser);
    echo "\n<br>Found deleted user source? ";
    if (! $data) { echo "no"; } else {
        print_r($data);
    }
    $data = GroboAuth\DataAccess::getUserSource($db, $users[2], $sources[0]);
    echo "\n<br>Found existing user source? ";
    if (! $data) { echo "no"; } else {
        print_r($data);
    }
    echo "\n<br>User authentication code: ".$data['Authentication_Code'];
    $hashedPassword = GroboAuth\DataAccess::hashPassword($data['Authentication_Code'], 10);
    echo "\n<br>Encoded password: ".$hashedPassword;
    echo "\n<br>Valid password? ".(GroboAuth\DataAccess::checkPassword($data['Authentication_Code'], $hashedPassword, 10));
    echo "\n<br>Invalid password? ".(GroboAuth\DataAccess::checkPassword("invalid", $hashedPassword, 10));
    
    
    // Test error conditions
    try {
        echo "\n<br>Removing a user with a bad user id";
        GroboAuth\DataAccess::removeUser($db, -3);
        echo "\n<br>Did not throw an exception";
    } catch (Base\ValidationException $e) {
        echo "\n<br>Caught the error: " . print_r($e, true);
    }
    
    

    // FIXME switch these to use the DataAccess instead.
    $wr_user =& WebRiffs\User::$INSTANCE;
    $wr_film =& WebRiffs\Film::$INSTANCE;
    $wr_filmVersion =& WebRiffs\FilmVersion::$INSTANCE;
    $wr_quip =& WebRiffs\Quip::$INSTANCE;
    $wr_quipVersion =& WebRiffs\QuipVersion::$INSTANCE;
    $wr_quipTag =& WebRiffs\QuipTag::$INSTANCE;
    $wr_tag =& WebRiffs\Tag::$INSTANCE;
    $wr_filmTag =& WebRiffs\FilmVersionTag::$INSTANCE;

    # No explicit DB layer for this, nor should there be.  This is for testing
    # only.
    $db->prepare('DELETE FROM QUIP_FILM_VERSION')->execute();
    $db->prepare('DELETE FROM QUIP_TAG')->execute();
    $db->prepare('DELETE FROM QUIP')->execute();
    $db->prepare('DELETE FROM FILM_VERSION_TAG')->execute();
    $db->prepare('DELETE FROM TAG')->execute();
    $db->prepare('DELETE FROM FILM_VERSION')->execute();
    $db->prepare('DELETE FROM FILM')->execute();
    $db->prepare('DELETE FROM USER')->execute();

    $data = $wr_user->create($db, array(
        'Username' => 'user a',
        'Contact' => 'eat@joes',
        'Ga_User_Id' => $user1,
        'Is_Site_Admin' => 0
    ));
    $wrUser1 = $data['User_Id'];
    echo "<br>Created wr user ".$wrUser1."\n";

    $data = $wr_film->create($db, array(
        'Name' => 'F For Fake',
        'Release_Year' => 1921,
        'Imdb_Url' => '/films/F_For_Fake',
        'Wikipedia_Url' => '/films/F_For_Fake',
    ));
    $wrFilm1 = $data['Film_Id'];
    echo "<br>Created wr film ".$wrFilm1."\n";

    $data = $wr_tag->create($db, array(
        'Name' => 'orson wells',
        'Author_User_Id' => $wrUser1
    ));
    $wrTag1 = $data['Tag_Id'];
    echo "<br>Created tag ".$wrTag1."\n";

    $data = $wr_filmVersion->create($db, array(
        'Film_Id' => $wrFilm1,
        'Parent_Film_Version_Id' => NULL,
        'Parent_Transparent' => 0,
        'Author_User_Id' => $wrUser1,
        'Name' => 'v1'
    ));
    $wrFilmVersion1 = $data['Film_Version_Id'];
    echo "<br>Created film version ".$wrFilmVersion1."\n";

    $data = $wr_filmTag->create($db, array(
        'Film_Version_Id' => $wrFilmVersion1,
        'Tag_Id' => $wrTag1,
        'Author_User_Id' => $wrUser1
    ));
    $wrFilmVersionTag1 = $data['Film_Version_Tag_Id'];
    echo "<br>Created film version tag ".$wrFilmVersionTag1."\n";
    
    $data = $wr_filmVersion->read($db, $wrFilmVersion1);
    echo "<br>Film Version data<ol>\n";
    if (! $data) {
        print_r($wr_filmVersion->errors);
        print_r($db->errorInfo());
    }
    foreach ($data as $col => $value) {
        echo "<li>".$col.":".$data[$col]."</li>\n";
    }
    echo "</ol>\n";
    

    // Bad input
    //$wr_quipTag->create($db, array(
    //    'Quip_Version_Id' => 10,
    //    'Tag_Id' => 11,
    //    'Author_User_Id' => $user1,
    //));


} catch (Exception $e) {
    echo 'Error: ' . $e;
}
?>


</body>
</html>
