<?php
/**
 * Opauth basic configuration file to quickly get you started
 * ==========================================================
 * To use: rename to opauth.conf.php and tweak as you like
 * If you require advanced configuration options, refer to opauth.conf.php.advanced
 */

require_once 'site.conf.php';

$opauthConfig = array(
/**
 * Path where Opauth is accessed.
 * - Begins and ends with /
 * - eg. if Opauth is reached via http://example.org/auth/, path is '/auth/'
 * - if Opauth is reached via http://auth.example.org/, path is '/'
 */
'path' => $siteConfig['path'] . 'auth/',

/**
 * Callback URL: redirected to after authentication, successful or otherwise
 */
'callback_url' => '{path}callback.php',

/**
 * A random string used for signing of $auth response.
 */
'security_salt' => $siteConfig['security_salt'],


/**
 * Higher value, better security, slower hashing;
 * Lower value, lower security, faster hashing.
 */
'security_iteration' => 300,

/**
 * Time limit for valid $auth response, starting from $auth response generation to validation.
 */
'security_timeout' => '2 minutes',


/**
 * Strategy
 * Refer to individual strategy's documentation on configuration requirements.
 *
 * eg.
 * 'Strategy' => array(
 *
 * 'Facebook' => array(
 * 'app_id' => 'APP ID',
 * 'app_secret' => 'APP_SECRET'
 * ),
 *
 * )
 *
 */
'Strategy' => array(
// Define strategies and their respective configs here

),
);
