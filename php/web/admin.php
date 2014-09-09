<html>
<head>
    <title>Super Secret Admin Setup Page</title>
</head>
<body>
<h1>TEMPORARY ADMIN PAGE</h1>
<h2>It's dangerous to go alone.</h2>
<?php

// Record to the web logs that this page was reached.  Admins - this can mean
// an attempt at a security breach.
error_log("INITIAL SITE SETUP PAGE ACCESSED!");

// ==========================================================================
// This page walks through all the configuration steps.  It should be read
// straight through to understand the different parts that are needed to
// setup the config.
// ==========================================================================


// ===========================================================================
// ===========================================================================
// Step 1: make sure that this page can continue to be accessed.  We do that
// by checking to see if the name of this file is the one that allows admin
// access.  If it was renamed, then that means the admin setup was completed,
// and no one should be able to run it again.

// ---------------------------------------------------------------------------
// Make sure that this wasn't incorrectly accessed after the page
// was removed.
if (basename(__FILE__) != "admin.php") {
?>
<p>
ERROR: this file is inaccessible because it has been renamed.  You need to
rename it to 'admin.php' and try again.
</p>
<?php
    die;
}

// ---------------------------------------------------------------------------
// The name of the file is right.

?>
<p>
This page is for the initial setup of the site.  Follow through all the
instructions on this page.  You may need to check your web server log file
if an error occurs during setup.
</p>


<?php
// ===========================================================================
// ===========================================================================
// Step 2: Check the site.conf.php file

// ---------------------------------------------------------------------------
// Does the file exist?
$siteConfPhpFile = __DIR__.'/../conf/site.conf.php';
if (! is_file($siteConfPhpFile)) {
?>
<p>
You don't have a <code>site.conf.php</code> file.  It should be located at
</p>
<pre><?= $siteConfPhpFile ?></pre>
<?php
    // ------------------------------------------------------------------------
    // Does the template file exist?
    $siteConfPhpTemplateFile = __DIR__.'/../conf/site.conf.php.template';
    if (! is_file($siteConfPhpTemplateFile)) {
?>
<p>
You also don't have the template file it's based on (<code><?= $siteConfPhpTemplateFile ?></code>).
That's fine, but it means that you need to find that file and either put it
on your website, or configure it yourself and add it to your website as the
<code>site.conf.php</code> file (see above for path).
</p>
<p>
Once you've corrected this problem, revisit this page to finish setting up
your site.
</p>
</body>
</html>
<?php
        die;
    }
    
    // ------------------------------------------------------------------------
    // Do we need to find out information to populate that file?
    if (! array_key_exists("x", $_POST)) {
        // Initial guess at the site path.
        $sitePath = "/";
        $pathTokens = preg_split('#/#', $_SERVER['REQUEST_URI'], -1,
                PREG_SPLIT_NO_EMPTY);
        if (sizeof($pathTokens) > 0) {
            // remove the filename (should be "admin.php") off the token.
            array_pop($pathTokens);
            $sitePath = '/'.implode('/', $pathTokens).'/';
        }
        
?>
<p>
We're going to try to set it up.
</p>

<p>
To setup this file, we need some information from you about your site.
</p>
<h3>Please pay close attention to the instructions.</h3>

<form action="admin.php" method="post">
<input type="hidden" name="x" value="set site conf">

<div style="border: solid 1px black;padding: 0.5em;">
<span><strong>Web Server Configuration</strong></span>
<div style="border: solid 1px black;">
<p>
<strong>Site path</strong> defines the path that users will use when reaching
your site.  So, if your website will be at <span style="color: blue;"><code>http://mysite.com/path/webriffs</code></span>,
then this path should be "/path/webriffs/".  If your website is at
<span style="color: blue;"><code>http://mysite.com/</code></span>,
then this path should be "/".  We filled this value in based on the current
address of this admin page, but you may want it to be different.
</p>
<p>
<label for="sitepath">Site path: </label><input id="sitepath" name="sitepath" type="text" value="<?=$sitePath?>">
</p>
</div>
</div>

<div style="border: solid 1px black;padding: 0.5em;">
<span><strong>MySQL Database Setup</strong></span>

<div style="border: solid 1px black;">
<strong>Database host</strong> refers to the hostname of the database that
your site will use.  Generally, this will be "localhost", but it might be
different.  If you use a non-standard port for the database, you'll need to
specify it here.
<p>
<label for="dbhost">Database Host: </label><input id="dbhost" name="dbhost" type="text" value="localhost">
</p>
</div>

<div style="border: solid 1px black;">
<p>
<strong>Database name</strong> is the name of the database on the database
host that will store the information for this site.
</p>
<p>
<label for="dbname">Database Name: </label><input id="dbname" name="dbname" type="text">
</p>
</div>

<div style="border: solid 1px black;">
<p>
<strong>Database normal user</strong> is the user with limited access to the
database.  It will be used in the normal operation of the site, and should have
only <code>CREATE</code>, <code>INSERT</code>, <code>UPDATE</code>, and
<code>DELETE</code> rights to the database.  For testing purposes, this can be
the admin user, or you can revoke the rights on this user after the site is
setup.
</p>
<p>
<label for="dbuser">Database Normal User: </label><input id="dbuser" name="dbuser" type="text">
<br>
<label for="dbpass">Database Normal Password: </label><input id="dbpass" name="dbpass" type="password">
</p>
</div>
</div>
<p>
<input type="submit" value="Setup the site config file">
</p>
</form>
</body>
</html>

<?php
        die;
    } elseif ($_POST["x"] == "set site conf") {
        // --------------------------------------------------------------------
        // The user posted the request
        $dbhost = $_POST["dbhost"];
        $dbname = $_POST["dbname"];
        $dbUser = $_POST["dbuser"];
        $dbPassword = $_POST["dbpass"];
        $dsn = 'mysql:host='.$dbhost.';dbname='.$dbname;
        
        // Test out the database settings.
        try {
            $db = new PDO($dsn,
                    $dbUser,
                    $dbPassword);
            // force the unloading of this database connection.
            unset($db);
        } catch (\Exception $e) {
            error_log("Admin Setup Exception: ".$e->getMessage());
            error_log($e->getTraceAsString());
?>
<p>
Invalid database settings - could not connect to the database.  Check your
webserver logs to see the full error.
</p><p>
Please go back and try again.
</p></body></html>
<?php
            die;
        }
        
?>
<p>Those database settings look okay.</p>
<p>
We're now setting up the site config for database connection [<?= $dsn ?>],
user [<?= $dbUser ?>]</p>
<p>This will add a temporary authentication source, but we'll revisit that
after the database is setup.</p>
<?php
        $templateFile = file_get_contents($siteConfPhpTemplateFile);
        if (! $templateFile) {
?>
<p>
We couldn't load the template file from
</p>
<pre><?= $siteConfPhpTemplateFile ?></pre>
<p>
Check to make sure the file permissions are correct on it, and try again.
</p></body></html>
<?php
            die;
        }
        
        // use "1" as a reasonable initial source id.
        $sourceId = 1;
        
        $templateFile = str_replace("@dsn@", $dsn, $templateFile);
        $templateFile = str_replace("@dbuser@", $dbUser, $templateFile);
        $templateFile = str_replace("@dbpassword@", $dbPassword, $templateFile);
        $templateFile = str_replace("@gasourceid@", (string) $sourceId, $templateFile);
        if (file_put_contents($siteConfPhpFile, $templateFile) === false) {
?>
<p>
There was a problem writing the template file to</p>
<pre><?= $siteConfPhpFile ?></pre>
<p>Make sure the webserver process has permissions to write files there.
If it doesn't, then you'll need to configure the configuration file yourself
and update it with the correct settings.
</p><p>
When you're finished setting up the permissions or the configuration file,
reload this page to continue where you left off.
</p>
<?php
            die;
        }
?>
<p>
The configuration file was updated.  Moving on.
</p>
<?php
    } else {
        // --------------------------------------------------------------------
        // Bad request
?>
<p>
You have reached this page in error.  Somehow, the site state doesn't match
the order in which you ran the configuration.  We expected you to be setting
up the site configuration file, but instead you were trying to
"<?= $_POST["x"] ?>".  If you can't find help to fix this issue here, you may
have to start over from the beginning.  Sorry!
</p>
<?php
        die;
    }
}


// ===========================================================================
// ===========================================================================
// Step 3: Check to make sure the configuration file is correct.

// ---------------------------------------------------------------------------
// Can we load the file as PHP source?
if (! include_once($siteConfPhpFile)) {
?>
<p>
There was a problem loading your configuration file:
</p>
<pre><?= $siteConfPhpFile ?></pre>
<p>
You can either remove it and use this page to attempt to recreate it, or
recreate it yourself.  You may need to check the webserver's error logs to
track down the error.
</p></body></html>
<?php
    die;
}


// ---------------------------------------------------------------------------
// Does the file create the siteConfig variable and the required parts?
if (! isset($siteConfig) ||
        ! array_key_exists("db_config", $siteConfig) ||
        ! array_key_exists("dsn", $siteConfig["db_config"]) ||
        ! array_key_exists("username", $siteConfig["db_config"]) ||
        ! array_key_exists("password", $siteConfig["db_config"])) {
?>
<p>
The configuration file doesn't define the <code>$siteConfig</code> value, or
the 'db_config' values inside that are mis-configured.
Double check the file:
</p>
<pre><?= $siteConfPhpFile ?></pre>
<p>
And check your webserver error logs to see where the problem lies.  Revisit this
page once you think it's been corrected.
</p></body></html>
<?php
    die;
}


// ---------------------------------------------------------------------------
// Do the db settings in the config file work?
$userDb = null;
try {
    // First, see if the database connection works.
    $userDb = new PDO($siteConfig['db_config']['dsn'],
            $siteConfig['db_config']['username'],
            $siteConfig['db_config']['password']);
} catch (\Exception $e) {
    error_log("Database connection creation Exception: ".$e->getMessage());
    error_log($e->getTraceAsString());
    ?>
<p>
Your database configuration in <code>$siteConfig['db_config']</code>, inside
<code><?= $siteConfPhpFile ?></code>, is not configured correctly, or the
database doesn't have permissions for access by the settings in that file.
Please correct it and try again, or remove that file, and refresh this page to
start it over.
</p>
<?php
    die;
}


// ===========================================================================
// ===========================================================================
// Step 4: Install the database schema
// TODO this needs to be standardized based on the db generation code.

// ---------------------------------------------------------------------------
// For now, use the db connection created above to see if this view exists,
// which touches most of the schema.
$data = null;
try {
    $userDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $userDb->prepare("SELECT COUNT(*) FROM V_QUIP_USER_PENDING");
    $res = $stmt->execute(array());
    // FIXME should check the result value.
} catch (\Exception $e) {
    // we expect this error, so let's not log it can possibly cause a panic.
    //error_log("Database test Exception (could be fine): ".$e->getMessage());
    //error_log($e->getTraceAsString());
?>
<p>
Your database doesn't look to be loaded yet.  Let's create that now.
FIXME this needs your admin user.  For now, if this fails, you'll have to
temporarily assign CREATE TABLE and CREATE VIEW permissions to the user.
Eventually, this will ask for your admin account if it fails to load with
the user account.
</p>
<?php
    try {
        $basesqld = __DIR__."/../sql/";
        foreach (scandir($basesqld, SCANDIR_SORT_ASCENDING) as $bd) {
            $d = $basesqld."/".$bd;
            //$d = $bd;
            if (is_dir($d) && substr($bd, 0, 1) != '.') {
                error_log("scanning [".$d."] / [".$bd."]");
                $files = scandir($d, SCANDIR_SORT_ASCENDING);
                foreach ($files as $ff) {
                    $f = $d . '/' . $ff;
                    //error_log("scanning [".$f."] / [".$ff."]");
                    if (is_file($f)) {
                        $sql = file_get_contents($f);
                        error_log("running sql ".$f);
                        $stmt = $userDb->prepare($sql);
                        $stmt->execute(array());
                    }
                }
            }
        }
    } catch (\Exception $ee) {
        error_log("Database load Exception: ".$ee->getMessage());
        error_log($ee->getTraceAsString());
?>
<p>
There was a problem loading up your database.  You should check the webserver
error log to track down the underlying problem.  You'll probably have to
recreate the database and start over again once the issue has been resolved.
</p></body></html>
<?php
        die;
    }
?>
<p>
The database is now loaded.  Fantastic!
</p>
<?php
}



// ===========================================================================
// ===========================================================================
// Step 5: Check the GA_SOURCE table.

?>
<p>
Now we're going to check the authentication source records.
</p>
<?php

// Grab the GA_SOURCE table.  Right now we only care about the "local" source
// name.  Eventually, this will be more robust.
$data = null;
try {
    $userDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $userDb->prepare("SELECT Ga_Source_Id, Source_Name FROM GA_SOURCE WHERE Source_Name = 'local'");
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $res = $stmt->execute(array());
    if ($res === false) {
?>
<p>
There was a really weird problem loading from the database.  It looks like your
PHP configuration for the PDO connections isn't right.  Check your webserver
logs to see how to go about fixing this problem.
</p></body></html>
<?php
        die;
    }
    $data = $stmt->fetchAll();
} catch (\Exception $e) {
    error_log("Database pull Exception: ".$e->getMessage());
    error_log($e->getTraceAsString());
?>
<p>
Woah.  There was a problem loading from the GA_SOURCE table.  Check your
webserver log to see the source of the issue.  You may need to start over.
</p></body></html>
<?php
    die;
}


$sourceId = null;
$needsSource = true;
if (sizeof($data) > 0) {
    // Because of our query, this should be the case.  But let's be certain.
    $row = $data[0];
    if ($row['Source_Name'] != 'local') {
        ?>
<p>
Your database doesn't seem to be working right, or the PHP-database code isn't
configured correctly.  Inspect your PHP installation and the webserver and
try this again.
</p></body></html>
<?php
            die;
    }
    $needsSource = false;
    $sourceId = intval($row['Ga_Source_Id']);
}
if ($needsSource) {
?>
<p>
Creating a new source ID for 'local'.
</p>
<?php
    try {
        $stmt = $userDb->prepare("INSERT INTO GA_SOURCE (".
            "Source_Name, Created_On, Last_Updated_On) VALUES (".
            "'local', NOW(), NULL)");
        $stmt->execute(array());
        $sourceId = intval($userDb->lastInsertId());
    } catch (\Exception $e) {
        error_log("Database insert GA_SOURCE Exception: ".$e->getMessage());
        error_log($e->getTraceAsString());
?>
<p>
Could not create the GA_SOURCE for 'local'.  Check your webserver logs to
track down the problem, and try again when it's been fixed.
</p></body></html>
<?php
        die;
    }
}



// ===========================================================================
// ===========================================================================
// Step 6: Compare the GA_SOURCE id against the config id.

if ($sourceId === null) {
?>
<p>
We didn't have an error loading the GA_SOURCE id for 'local', but we didn't
retrieve a value, either.  Check your webserver logs for a problem, and try
again when it's been fixed.
</p></body></html>
<?php
    die;
}


if ($sourceId != $siteConfig['sources']['local']['id']) {
?>
<p>
So, we ran into a bit of a problem.  Fortunately, it's easy to fix.  When the
site config file:
</p>
<pre><?= $siteConfPhpFile ?></pre>
<p>
was setup, the local source id wasn't set to the correct id.  You'll need to
edit that config file, and change the <code>sources</code> -
<code>local</code> - <code>id</code>.  The value is currently set to:
</p>
<div style="align: center; color: dark-green;"><?= $siteConfig['sources']['local']['id'] ?></div>
<p>
When it should be set to:
</p>
<div style="align: center; color: dark-green;"><?= $sourceId ?></div>
<p>
Please fix this and reload this page.  We'll check it again and let you know
if there's any other problems.
</p></body></html>
<?php
}

if ($needsSource) {
?>
<p>We successfully created the new source ID,
and it looks like your site config is compatible.
Fantastic!  We're almost done.</p>
<?php
} else {
?>
<p>There is an existing authentication source which is
compatible with your site config.
Fantastic!  We're almost done.</p>
<?php
}

// ===========================================================================
// ===========================================================================
// Step 7: Create some initial data


require_once(__DIR__."/../lib/Tonic/Exception.php");
//require_once(__DIR__."/../lib/Tonic/Resource.php");
require_once(__DIR__."/../src/Base/BaseDataAccess.php");
require_once(__DIR__."/../src/Base/DboBase.php");
require_once(__DIR__."/../src/Base/ValidationException.php");
require_once(__DIR__."/../src/WebRiffs/Access.php");
require_once(__DIR__."/../src/WebRiffs/AdminLayer.php");

$filenames = array(
    __DIR__.'/../dbo/GroboAuth/*.php',
    __DIR__.'/../dbo/GroboVersion/*.php',
    __DIR__.'/../dbo/WebRiffs/*.php',
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

try {
    $data = WebRiffs\AdminLayer::getLinkNamed($userDb, 'wikipedia-en');
    if ($data === null) {
        WebRiffs\AdminLayer::createLink($userDb, 'wikipedia-en',
            'Open Encyclopedia (English)', 'http://en.wikipedia.org/wiki/',
            // Note: this leaves out articles in international characters.
            '^[a-zA-Z0-9\\$_-\\(\\)]+$',
            False);
    }
    
    $data = WebRiffs\AdminLayer::getLinkNamed($userDb, 'imdb.com');
    if ($data === null) {
        WebRiffs\AdminLayer::createLink($userDb, 'imdb.com',
            'International Movie Database', 'http://imdb.com/title/',
            '^[a-zA-Z0-9]+$',
            False);
    }

    $data = WebRiffs\AdminLayer::getLinkNamed($userDb, 'YouTube');
    if ($data === null) {
        $data = WebRiffs\AdminLayer::createLink($userDb, 'YouTube',
                'Google YouTube', 'https://youtube.com/watch?v=',
                '^[a-zA-Z0-9][a-zA-Z0-9_-]+$',
                True);
    }
} catch (\Exception $e) {
    error_log("Database create link Exception: ".$e->getMessage());
    error_log($e->getTraceAsString());
?>
<p>
There was a problem when creating some initial data.  Check your webserver
error logs, correct the issues, and retry the operation.
</p></body></html>
<?php
        die;
}



// ===========================================================================
// ===========================================================================
// Step 8: Create an admin user

if (! array_key_exists("x", $_POST)) {
?>
<p>
So, you've configured your properties, but you still need an administrative
user.  This is the place where you put the user information.
</p>
<form action="admin.php" method="post">
<input type="hidden" name="x" value="create admin user">
<br><label for="user">Username: </label><input id="user" name="username" type="text">
<br><label for="password">Password: </label><input id="password" name="password" type="password">
<br><label for="email">Email: </label><input id="email" name="email" type="text">
<input type="submit" value="Create the user and finish setup">
</form>
</body></html>
<?php
    die;
} elseif ($_POST["x"] == "create admin user") {
    $username = $_POST["username"];
    $password = $_POST["password"];
    $email = $_POST["email"];
    
    require_once(__DIR__."/../lib/Tonic/Exception.php");
    //require_once(__DIR__."/../lib/Tonic/Resource.php");
    require_once(__DIR__."/../src/Base/DboBase.php");
    require_once(__DIR__."/../src/Base/ValidationException.php");
    require_once(__DIR__."/../dbo/GroboAuth/GaUser.php");
    //require_once(__DIR__."/../src/dbo/GroboAuth/GaSource.php");
    require_once(__DIR__."/../dbo/GroboAuth/GaUserSource.php");
    require_once(__DIR__."/../dbo/WebRiffs/User.php");
    require_once(__DIR__."/../dbo/WebRiffs/UserAccess.php");
    require_once(__DIR__."/../dbo/WebRiffs/UserAttribute.php");
    require_once(__DIR__."/../src/GroboAuth/DataAccess.php");
    require_once(__DIR__."/../src/WebRiffs/AuthenticationLayer.php");
    require_once(__DIR__."/../src/WebRiffs/Access.php");
    
    $encPassword = WebRiffs\AuthenticationLayer::hashPassword($password);
    
    try {
        $userId = WebRiffs\AuthenticationLayer::createUser($userDb, $username,
                $siteConfig['sources']['local']['id'], $username, $encPassword,
                $email, WebRiffs\Access::$PRIVILEGE_ADMIN);
    } catch (\Exception $e) {
        error_log("Database create user Exception: ".$e->getMessage());
        error_log($e->getTraceAsString());
?>
<p>
There was a problem when creating the administrative user.  Check your webserver
logs and ... man, I'm getting tired of writing that phrase over and over in this
file.  Anyway, you can't continue the setup until you get this right, for some
reason, even though user rights aren't being used in the site yet.
</p></body></html>
<?php
        die;
    }
    
?>
<p>
User created.  Let's see what we have in store for you next.
</p>
<?php
} else {
?>
<p>
You reached this page in error.  Try reloading your browser and see if that
fixes things.
</p></body></html>
<?php
    die;
}




// ===========================================================================
// ===========================================================================
// Step 9: Rename the admin.php file.


if (rename(__FILE__, __DIR__.'/.ht_admin.php')) {
    // Note the intentional misspelling.
?>
<h1>CONGRATURATIONS!</h1>
<p>
This site is now configured.  You will not be able to revisit this page,
because we just renamed it to '.ht_admin.php' for security reasons.  If you
need to rerun the administrative scripts, you'll need to refresh everything.
<p>
<p>
<a href="index.html">Click here to visit your new site!</a>
</p></body></html>
<?php
    die;
} else {
?>
<h3>Failed to rename the file. DO THIS MANUALLY.</h3>
<p>
You should really rename this file.
</p>
<pre><?= __FILE__ ?></pre>
<p>
The site won't work otherwise, and it
leaves open a big gaping security risk.
</p>
</body></html>
<?php
    die;
}
?>

<p>
You reached this spot in error.  Something went horribly wrong with the
installer, Great Cthulhu was released from his eternal sleep,
Narlyhotep isn't far behind, and you are now seeing this message.
</p>
</body>
</html>

<?php
die;


