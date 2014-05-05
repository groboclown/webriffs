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
    #require_once '../src/Base/all_base.php';
    require_once '../lib/Tonic/Exception.php';
    require_once '../src/Base/DboBase.php';
    require_once '../src/Base/ValidationException.php';
    require_once '../dbo/GroboAuth/GaUser.php';
    require_once '../dbo/WebRiffs/QuipTag.php';
    require_once '../dbo/WebRiffs/User.php';
    require_once '../dbo/WebRiffs/Film.php';
    require_once '../dbo/WebRiffs/FilmVersion.php';
    require_once '../dbo/WebRiffs/FilmVersionTag.php';
    require_once '../dbo/WebRiffs/Quip.php';
    require_once '../dbo/WebRiffs/QuipVersion.php';
    require_once '../dbo/WebRiffs/Tag.php';

    #use GroboAuth;

    $db = new PDO($siteConfig['db_config']['dsn'],
        $siteConfig['db_config']['username'],
        $siteConfig['db_config']['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $gauser = new GroboAuth\GaUser;

    echo '<br>User row count: ' . $gauser->countRows($db);

    # No explicit DB layer for this, nor should there be
    $db->prepare('DELETE FROM GA_USER')->execute();

    $mc = $gauser->countRows($db);
    echo "<br>User count should be 0, found " . $mc;

    $data = $gauser->create($db, array());
    $user1 = $data['Ga_User_Id'];
    echo "<br>Created user ".user1."\n";

    $data = $gauser->readAll($db);
    echo "<br>Read ".$data;

    $data = $gauser->create($db, array());
    echo "<br>Created:<ol>";
    foreach ($data as $col => $value) {
        echo "<li>".$col.":".$data[$col]."</li>\n";
    }
    $user2 = $data['Ga_User_Id'];
    echo "</ol>\n";

    $data = $gauser->readAll($db);
    echo "<br>Read:<ol>";
    foreach ($data as $row) {
        echo "<li><ol>\n";
        foreach ($row as $col => $value) {
            echo "<li>".$col.":".$row[$col]."</li>\n";
        }
        echo "</ol></li>\n";
    }
    echo "</ol>";

    $mc = $gauser->countRows($db);
    echo "<br>User count should be 2, found " . $mc . "\n";

    $wr_user = new WebRiffs\User;
    $wr_film = new WebRiffs\Film;
    $wr_filmVersion = new WebRiffs\FilmVersion;
    $wr_quip = new WebRiffs\Quip;
    $wr_quipVersion = new WebRiffs\QuipVersion;
    $wr_quipTag = new WebRiffs\QuipTag;
    $wr_tag = new WebRiffs\Tag;
    $wr_filmTag = new WebRiffs\FilmVersionTag;

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
        'Parent_Film_Version_Id' => nil,
        'Parent_Transparent' => 0,
        'Author_User_Id' => $wrUser2,
        'Name' => 'v1'
    ));
    $wrFilmVersion1 = $data['Film_Version_Id'];
    echo "<br>Created tag ".$wrFilmVersion1."\n";

    $data = $wr_filmTag->create($db, array(
        'Film_Version_Id' => $wrFilmVersion1,
        'Tag_Id' => $wrTag1,
        'Author_User_Id' => $wrUser1
    ));
    $wrFilmVersionTag1 = $data['Film_Version_Tag_Id'];
    echo "<br>Created film version tag ".$wrFilmVersionTag1."\n";


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
