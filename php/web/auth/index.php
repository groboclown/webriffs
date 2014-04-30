<?php

/*
 * Initial user login / user creation page.
 *
 * For the moment, to get the app running, there's only a username you enter,
 * and that will authenticate you.
 */

require_once '../../conf/site.conf.php';

$cookie =& $_COOKIE;
$post =& $_POST;
$server =& $_SERVER;

if ($cookie['authchallenge']) {

?>

<?php

} else {

    // assume it's a valid login
    setcookie('authchallenge', $challengecookie, 0, $siteConfig['path']);

?>
<html>
<body>
<p>Logged in as <?= $user ?>. <a href="../index.html">Go back</a></p>
</body>
</html>
<?php

}
