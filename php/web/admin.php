<html>
<head>
    <title>Super Secret Admin Page</title>
</head>
<body>
<?php
// Make sure that this wasn't incorrectly accessed after the page
// was removed.
if (basename(__FILE__) != "admin.php") {
    echo "ERROR: this file is inaccessible.  Rename to 'admin.php' and try again.";
    die;
}
?>
<h1>TEMPORARY ADMIN PAGE</h1>
<?php

if (array_key_exists("x", $_POST)) {
    require_once '../conf/site.conf.php';
    $container['db_config'] = array(
        'dsn' => $siteConfig['db_config']['dsn'],
        'username' => $siteConfig['db_config']['username'],
        'password' => $siteConfig['db_config']['password']
    );
    
    $db = new PDO($siteConfig['db_config']['dsn'],
            $siteConfig['db_config']['username'],
            $siteConfig['db_config']['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($_POST["x"] == "rename") {
        if (rename(__FILE__, __DIR__.'/.ht_admin.php')) {
            echo "You successfully renamed this page so that it shouldn't be ".
                    "accessible anymore.  To rerun any admin task, rename the ".
                    "file <code>".__DIR__."/.ht_admin.php</code> to <code>".
                    __FILE__."</code> and reload the page.";
            die;
        } else {
            echo "Failed to rename the file.  Reload this page and try again.";
            die;
        }
    } elseif ($_POST["x"] == "schema") {
        $dirs = array("00", "01", "02");
        foreach ($dirs as $dirname) {
            $d = __DIR__."/../sql/".$dirname;
            $files = scandir($d, SCANDIR_SORT_ASCENDING);
            foreach ($files as $f) {
                $sql = file_get_contents($d . "/" . $f);
                $db->exeecute($sql);
            }
        }
    }
}


?>
<form action="admin.php" method="post">
<input type="hidden" name="x" value="rename">
<h1>THIS FILE SHOULD ONLY EXIST DURING INITIAL SETUP.</h1>
<h3>Rename this file to prevent a security hole.</h3>
<input type="submit" value="Click here to rename this file.">
</form>


<hr>

<form action="admin.php" method="post">
<input type="hidden" name="x" value="schema">
<h1>Initialize the Database</h1>
<p>Before you can start the setup, make sure your
<code>conf/site.conf.php</code> file is setup correctly with your settings,
and that your database was created.
Then, click this button to initialize the database.  You should only do this
once.</p>
<input type="submit" value="Click here to load the database schema">
</form>


</body>
</html>
