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

    #use GroboAuth;

    $db = new PDO($siteConfig['db_config']['dsn'],
        $siteConfig['db_config']['username'],
        $siteConfig['db_config']['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $gauser = new GroboAuth\GaUser;

    echo $gauser->countRows($db);
} catch (Exception $e) {
    echo 'Error: ' . $e;
}
?>


</body>
</html>
