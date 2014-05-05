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
    $user1 = $data['User_Id'];
    echo "<br>Created user ".user1."\n";

    $data = $gauser->readAll($db);
    echo "<br>Read ".$data;

    $data = $gauser->create($db, array());
    echo "<br>Created:<ol>";
    foreach ($data as $col => $value) {
        echo "<li>".$col.":".$data[$col]."</li>\n";
    }
    $user2 = $data['User_Id'];
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

    // Bad input
    $quiptag = new WebRiffs\QuipTag;

    $quiptag->create($db, array(
        'Quip_Version_Id' => 10,
        'Tag_Id' => 11,
        'Author_User_Id' => $user1,
    ));


} catch (Exception $e) {
    echo 'Error: ' . $e;
}
?>


</body>
</html>
