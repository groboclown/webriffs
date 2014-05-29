<?php

namespace GroboAuth;

use PBO;
use Base;


require_once (__DIR__ . '/../../lib/phpass/PasswordHash.php');

// Requires "PasswordHash" for the hashing functions.
// Requires the dbo files for GroboAuth.
class DataAccess {


    /**
     * Creates a new GA user in the system.
     * Returns the id of the newly
     * created record.
     *
     * @return s the id
     */
    public static function createUser($db) {
        try {
            $data = GaUser::$INSTANCE->create($db);
            DataAccess::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem during user creation'
                    )));
        } catch (Exception $e) {
            error_log(print_r($e, true));
            throw new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem during user creation'
                ));
        }
        return $data["result"];
    }


    /**
     * Removes the user and its dependent columns from the GA* tables.
     * Any other foreign keys on this user must be removed first.
     */
    public static function removeUser($db, $id) {
        $userSources = GaUserSource::$INSTANCE->readBy_Ga_User_Id($db, $id);
        DataAccess::checkError($userSources,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem accessing the user'
                )));
        foreach ($userSources["result"] as $usData) {
            $usId = intval($usData["Ga_User_Source_Id"]);
            DataAccess::removeUserSource($db, $usId);
        }
        $data = GaUser::$INSTANCE->remove($db, $id);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem removing the user'
                )));
        $count = $data["result"];
        if ($count <= 0) {
            error_log("Did not remove any rows for ga_user " . $id);
            throw new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem removing the user'
                ));
        }
    }


    /**
     * Administration function to create a new authentication source.
     * This
     * should only be called when absolutely required. For performance
     * purposes, the system should store the source ID (returned by this
     * function) along with the source name (passed into this function)
     * in the source code.
     *
     * @return s the id
     */
    public static function createSource($db, $sourceName) {
        try {
            $data = GaSource::$INSTANCE->create($db, $sourceName);
            DataAccess::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem creating the source (already exists?)'
                    )));
            return $data['result'];
        } catch (Exception $e) {
            throw new Base\ValidationException(
                array(
                    'sourceName' => 'already exists'
                ));
        }
    }


    /**
     * Sets the source information for a user.
     * If it is already assigned, it is
     * updated, otherwise it is created.
     *
     * The authentication code should be properly encoded by the callee
     * depending on the contents. This method will NOT encrypt or hash the
     * value. IT IS UP TO THE CALLEE TO PERFORM PROPER ENCRYPTION OR HASHING.
     * This class provides helper methods to encrypt, decrypt, and one-way hash
     * values.
     *
     * This does not perform any validation on the username or authentication
     * code. That should be done by the callee.
     *
     * Care should be taken when changing the username. That may not be a valid
     * use case in all situations.
     *
     * @param
     *            userId (int) the GA_USER id
     * @param
     *            sourceId (int) the GA_SOURCE id
     * @param
     *            username the username for the source
     * @param
     *            authenticationCode the authentication code associated with the
     *            user name for this source. If no code should be stored, then a
     *            blank string will be sufficient.
     * @return s the id of the user source
     */
    public static function setUserSource($db, $userId, $sourceId, $username,
        $authenticationCode) {
        if (strlen($username) > 2046) {
            throw new Base\ValidationException(
                array(
                    'username' => 'invalid username'
                ));
        }
        if (strlen($authenticationCode) > 2048) {
            throw new Base\ValidationException(
                array(
                    'authentication code' => 'authentication code too long'
                ));
        }
        
        $data = GaUserSource::$INSTANCE->readBy_Ga_User_Id_x_Ga_Source_Id($db,
            $userId, $sourceId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem changing the user access'
                )));
        $rows = $data['result'];
        
        $retId = null;
        if (sizeof($rows) <= 0) {
            // create
            $data = GaUserSource::$INSTANCE->create($db, $userId,
                $sourceId, $username, $authenticationCode);
            $retId = $data['result'];
        } else {
            // update
            $retId = intval($rows[0]['Ga_User_Source_Id']);
            $data = GaUserSource::$INSTANCE->update($db, $retId, $username,
                $authenticationCode);
        }
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem changing the user access'
                )));
        //error_log("Ga_User_Source_Id = ".$data['Ga_User_Source_Id']);
        return $retId;
    }


    /**
     * Returns an array containing 'Ga_User_Source_Id', 'Username',
     * 'Authentication_Code', or false if no such record exists.
     */
    public static function getUserSource($db, $userId, $sourceId) {
        //error_log("reading user source for [".$userId."] [".$sourceId."]");
        $data = GaUserSource::$INSTANCE->readBy_Ga_User_Id_x_Ga_Source_Id(
            $db, $userId, $sourceId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem changing the user access'
                )));
        if (sizeof($data['result']) <= 0) {
            return false;
        }
        $row = $data['result'][0];
        
        return array(
            'Ga_User_Id' => intval($row['Ga_User_Id']),
            'Ga_Source_Id' => intval($row['Ga_Source_Id']),
            'Ga_User_Source_Id' => intval($row['Ga_User_Source_Id']),
            'Username' => $row['Username'],
            'Authentication_Code' => $row['Authentication_Code']
        );
    }


    /**
     * Removes the source references for this user id.
     */
    public static function removeUserSource($db, $id) {
        $data = GaSession::$INSTANCE->readBy_Ga_User_Source_Id($db, $id);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user'
                )));
        $sessions = $data['result'];
        foreach ($sessions as $sessionData) {
            $sessionId = intval($sessionData["Ga_Session_Id"]);
            $data = GaSession::$INSTANCE->remove($db, $sessionId);
            DataAccess::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem removing the user sessions'
                    )));
        }
        $data = GaPasswordRequest::$INSTANCE->readBy_Ga_User_Source_Id($db, $id);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user'
                )));
        $pwrequests = $data['result'];
        foreach ($pwrequests as $pwData) {
            $pwrId = intval($pwData["Ga_Password_Request_Id"]);
            $data = GaPasswordRequest::$INSTANCE->remove($db, $pwrId);
            DataAccess::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem removing the password requests'
                    )));
        }
        $data = GaLoginAttempt::$INSTANCE->readBy_Ga_User_Source_Id($db, $id);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user'
                )));
        $las = $data['result'];
        foreach ($las as $laData) {
            $laId = intval($laData["Ga_Login_Attempt_Id"]);
            $data = GaLoginAttempt::$INSTANCE->remove($db, $laId);
            DataAccess::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem removing the logins'
                    )));
        }
        $data = GaUserSource::$INSTANCE->remove($db, $id);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem removing the user'
                )));
    }


    /**
     * For administration purposes.
     */
    public static function countUserSources($db) {
        $data = GaUserSource::$INSTANCE->countAll($db);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user access'
                )));
        return $data['result'];
    }


    /**
     * For administration purposes.
     */
    public static function getUserSources($db, $start, $end) {
        $data = GaUserSource::$INSTANCE->readAll($db, false, $start, $end);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user sources'
                )));
        return $data['result'];
    }


    /**
     * Creates an entry in the database for a request for a new
     * password (not handled).
     * This does not return the
     * actual expiration time.
     *
     * TODO pending password requests should prevent normal log ins?
     */
    public static function createPasswordRequest($db, $userSourceId,
        $expirationMinutes) {
        $secretKey = createSecretKey();
        // ensure the secret key is not already used.
        while (hasPasswordSecretKey($db, $secretKey)) {
            $secretKey = createSecretKey();
        }
        
        $data = GaPasswordRequest::$INSTANCE->create($db, $userSourceId,
            $secretKey, 0, $expirationMinutes);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the password request'
                )));
        return array(
            'Ga_Login_Attempt_Id' => $data['result'],
            'Ga_User_Source_Id' => $userSourceId,
            'Secret_Key' => $secretKey
        );
    }


    private static function hasPasswordSecretKey($db, $secretKey) {
        $data = GaPasswordRequest::$INSTANCE->countBy_Secret_Key($db,
            $secretKey);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the password request'
                )));
        return $data['result'] > 0;
    }


    public static function getUserSourceForPasswordRequestSecretKey($db,
        $secretKey) {
        $data = GaPasswordRequest::$INSTANCE->readBy_Secret_Key($db, $secretKey);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the password request'
                )));
        if (sizeof($data['result']) != 1) {
            // either there was no data (no request), or
            // there was a data integrity error
            return false;
        }
        return intval($data['result'][0]['Ga_User_Source_Id']);
    }


    /**
     * Administration function to see all the active password requests.
     */
    public static function getAllActivePasswordRequests($db) {
        $data = VGaPasswordRequest::$INSTANCE->readAll($db);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the requests'
                )));
        return $data['result'];
    }


    /**
     * Administration function to see all the active password requests for
     * a given source.
     */
    public static function getActivePasswordRequestsForSource($db, $sourceId) {
        $data = VGaPasswordRequest::$INSTANCE->readBy_Ga_Source_Id($db,
            $sourceId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the requests'
                )));
        return $data['result'];
    }


    /**
     */
    public static function getActivePasswordRequestsForUserSource($db,
        $userSourceId) {
        $data = VGaPasswordRequest::$INSTANCE->readBy_Ga_User_Source_Id($db,
            $userSourceId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the requests'
                )));
        return $data['result'];
    }


    /**
     * When one password request for a user-source is completed, ALL
     * the password requests are marked as handled.
     */
    public static function handlePasswordRequest($db, $userSourceId, $secretKey) {
        // TODO Should be handled in 2 queries.
        $data = DataAccess::getActivePasswordRequestsForUserSource($db,
            $userSourceId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the requests'
                )));
        $rows = $data['result'];
        $direct = false;
        $found = false;
        foreach ($rows as $row) {
            $found = true;
            if ($row['Secret_Key'] == $secretKey) {
                // directly handled
                $data = GaPasswordRequest::$INSTANCE->update(
                    $db, $row['Ga_Password_Request_Id'], 1);
                DataAccess::checkError($data,
                    new Base\ValidationException(
                        array(
                            'unknown' => 'there was an unknown problem updating the requests'
                        )));
                $direct = true;
            } else {
                // indirectly handled
                $data = GaPasswordRequest::$INSTANCE->update(
                    $db, $row['Ga_Password_Request_Id'], 2);
                DataAccess::checkError($data,
                    new Base\ValidationException(
                        array(
                            'unknown' => 'there was an unknown problem updating the requests'
                        )));
            }
        }
        return ($found ? ($direct ? 1 : 2) : 0);
    }


    /**
     * Returns all the password change requests for a user, including whether
     * it was handled or not, ordered by most recent first.
     */
    public static function getPasswordChangeRequests($db, $userSourceId, $start,
        $end) {
        $data = GaPasswordRequest::$INSTANCE->readBy_Ga_User_Source_Id($db,
            "Expires_On DESC", $start, $end);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the requests'
                )));
        return $data['result'];
    }
    

    // FIXME include the user agent, remote address, forwarded for information
    public static function recordLoginAttempt($db, $userSourceId,
        $User_Agent, $Remote_Address, $Forwarded_For, $wasSuccessful) {
        // sanitize data
        if (!is_string($User_Agent) || !is_string($Remote_Address) ||
             !is_string($Forwarded_For)) {
            error_log(
                "bad data for one of these: useragent: [" . $User_Agent .
                 "], remoteaddr: [" . $Remote_Address . "], forward: [" .
                 $Forwarded_For . "]");
            throw new Base\ValidationException(
                array(
                    'unknown' => 'invalid user signature'
                ));
        }
        if (strlen($User_Agent) > 64) {
            $User_Agent = substr($User_Agent, 0, 64);
        }
        if (strlen($Remote_Address) > 2048) {
            $Remote_Address = substr($Remote_Address, 0, 2048);
        }
        if (strlen($Forwarded_For) > 2048) {
            $Forwarded_For = substr($Forwarded_For, 0, 2048);
        }
        
        $data = GaLoginAttempt::$INSTANCE->create($db, $userSourceId,
            $User_Agent, $Remote_Address, $Forwarded_For, $wasSuccessful ? 1 : 0);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the login'
                )));
        return true;
    }


    public static function getLoginAttemptsFor($db, $userSourceId,
        $timeLimitMinutes = -1) {
        // FIXME
        // limited paging support.  Pull in all the attempts (-1), or the
        // N most recent attempts, which means sorting in reverse order.
        // This allows checking if the M most recent attempts were invalid,
        // forcing a temporary login ban.
    }


    /**
     * Renews the current session for the user with the given authentication
     * credentials, and returns the user + source + session information.
     * This will also query the login attempts for the user, to see if the user
     * account is locked out (lock out logic is performed by the application,
     * as well as banning offences).
     *
     * If the session is expired, or the user was never logged in,
     * then false is returned.
     */
    public static function getUserForSession($db, $userAgent, $remoteAddress,
        $forwardedFor, $authorizationChallenge, $sessionRenewalMinutes,
        $loginAttemptsMinutes) {
        // sanitize data
        if (!is_string($userAgent) || !is_string($remoteAddress) ||
             !is_string($forwardedFor) || !is_string($authorizationChallenge)) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'invalid user signature'
                ));
        }
        if (strlen($userAgent) > 64) {
            $userAgent = substr($userAgent, 0, 64);
        }
        if (strlen($remoteAddress) > 2048) {
            $remoteAddress = substr($remoteAddress, 0, 2048);
        }
        if (strlen($forwardedFor) > 2048) {
            $forwardedFor = substr($forwardedFor, 0, 2048);
        }
        
        $data = VGaValidSession::$INSTANCE->readBy_User_Agent_x_Remote_Address_x_Forwarded_For_x_Authorization_Challenge(
            $db, $userAgent, $remoteAddress, $forwardedFor,
            $authorizationChallenge);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user session'
                )));
        $data = $data['result'];
        if (sizeof($data) < 0) {
            error_log(
                "no rows for [" . $userAgent . "] [" . $remoteAddress . "] [" .
                     $forwardedFor . "] [" . $authorizationChallenge . "]");
            return false;
        }
        if (sizeof($data) > 1) {
            // invalid setup.  Expire those sessions, and report as not logged in.
            foreach ($data as $row) {
                DataAccess::expireSession($db, $row['Ga_Session_Id']);
            }
            error_log(
                "multiple rows for [" . $userAgent . "] [" . $remoteAddress .
                     "] [" . $forwardedFor . "] [" . $authorizationChallenge .
                     "]");
            return false;
        }
        $sessionId = intval($data[0]['Ga_Session_Id']);
        $userSourceId = intval($data[0]['Ga_User_Source_Id']);
        DataAccess::renewSession($db, $sessionId, $sessionRenewalMinutes);
        $loginAttempts = DataAccess::getLoginAttemptsFor($db, $userSourceId,
            $loginAttemptsMinutes);
        if (!$loginAttempts) {
            $loginAttempts = array();
        }
        $userId = intval($data[0]['Ga_User_Id']);
        $sourceId = intval($data[0]['Ga_Source_Id']);
        $ret = array(
            'Ga_Session_Id' => $sessionId,
            'Ga_User_Id' => $userId,
            'Ga_Source_Id' => $sourceId,
            'Login_Attempts' => $loginAttempts,
            'Authentication_Challenge' => $authorizationChallenge
        );
        
        // FIXME DEBUG
        error_log(
            "valid session for [" . $userAgent . "] [" . $remoteAddress . "] [" .
                 $forwardedFor . "] [" . $authorizationChallenge . "]");
        
        return $ret;
    }


    public static function logoutSession($db, $sessionId) {
        // For now, just expire the session
        DataAccess::expireSession($db, $sessionId);
    }


    public static function countSessions($db, $userSourceId) {
        // FIXME
    }


    public static function getSessions($db, $userSourceId, $start, $end) {
        // FIXME
    }


    public static function expireSession($db, $sessionId) {
        // Force the session expiration time to be a lower number.
        // TODO the expiration of sessions should be better tracked.
        $data = GaSession::$INSTANCE->update($db, $sessionId, -10000);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user session'
                )));
    }


    public static function renewSession($db, $sessionId, $sessionRenewalMinutes) {
        $data = GaSession::$INSTANCE->update($db, $sessionId,
            $sessionRenewalMinutes);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user session'
                )));
        if ($data['rowcount'] != 1) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user session'
                ));
        }
    }


    /**
     * Returns the session ID for the given information.
     * If the session
     * already exists, it will be expired. If another session shares the
     * authorization challenge, then "false" is returned.
     */
    public static function createSession($db, $userSourceId, $userAgent,
        $remoteAddress, $forwardedFor, $authorizationChallenge,
        $expirationInMinutes) {
        if (!is_int($expirationInMinutes) || $expirationInMinutes < 1) {
            throw new Base\ValidationException(
                array(
                    'expiration' => 'invalid expiration time'
                ));
        }
        
        if (!is_string($authorizationChallenge) ||
             strlen($authorizationChallenge) > 2048) {
            throw new Base\ValidationException(
                array(
                    'authorization challenge' => 'invalid generation of the authorization challenge'
                ));
        }
        
        // sanitize data
        if (!is_string($userAgent) || !is_string($remoteAddress) ||
             !is_string($forwardedFor)) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'invalid user signature'
                ));
        }
        if (strlen($userAgent) > 64) {
            $userAgent = substr($userAgent, 0, 64);
        }
        if (strlen($remoteAddress) > 2048) {
            $remoteAddress = substr($remoteAddress, 0, 2048);
        }
        if (strlen($forwardedFor) > 2048) {
            $forwardedFor = substr($forwardedFor, 0, 2048);
        }
        

        // Invalidate existing sessions for this user signature
        $data = GaSession::$INSTANCE->readBy_Ga_User_Source_Id_x_User_Agent_x_Remote_Address_x_Forwarded_For(
            $db, $userSourceId, $userAgent, $remoteAddress, $forwardedFor);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user session'
                )));
        foreach ($data['result'] as $row) {
            // FIXME for testing purposes keep this commented out
            //DataAccess::expireSession($db, $row['Ga_Session_Id']);
        }
        
        $data = GaSession::$INSTANCE->create($db, $userSourceId, $userAgent,
            $remoteAddress, $forwardedFor, $authorizationChallenge,
            $expirationInMinutes);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem with the user session'
                )));
        return intval($data);
    }
    

    // -----------------------------------------------------------------------
    // Helper Functions
    


    /**
     * Helper function to encrypt values.
     * "key" is the private password that
     * will encrypt the "string".
     *
     * THIS SHOULD NEVER BE USED FOR PASSWORDS.
     */
    public static function encrypt($key, $string) {
        $iv = mcrypt_create_iv(
            mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC),
            MCRYPT_DEV_URANDOM);
        
        $encrypted = ase64_encode(
            $iv . mcrypt_encrypt(MCRYPT_RIJNDAEL_256,
                hash('sha256', $key, true), $string, MCRYPT_MODE_CBC, $iv));
        return $encrypted;
    }


    /**
     * Helper function to decrypt the values encrypted by "encrypt".
     */
    public static function decrypt($key, $encrypted) {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0,
            mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC));
        
        $decrypted = rtrim(
            mcrypt_decrypt(MCRYPT_RIJNDAEL_256, hash('sha256', $key, true),
                substr($data,
                    mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC)),
                MCRYPT_MODE_CBC, $iv), "\0");
        return $decrypted;
    }


    /**
     * Helper function to cryptographically hash a value.
     *
     * $iterations is an integer between 4 and 1000. 8 is a good number to use.
     */
    public static function hashPassword($password, $iterations,
        $portable = FALSE) {
        $t_hasher = new \PasswordHash($iterations, $portable);
        $hash = $t_hasher->HashPassword($password);
        return $hash;
    }


    /**
     *
     * @param $passwordToCheck the
     *            password passed in by the user, for
     *            authenticating against the hash.
     * @param $hash the
     *            value returned by hashPassword
     * @param $iterations Base-2
     *            logarithm of the iteration count used for
     *            password stretching
     * @return s TRUE if valid, FALSE if not.
     */
    public static function checkPassword($passwordToCheck, $hash, $iterations,
        $portable = FALSE) {
        $t_hasher = new \PasswordHash($iterations, $portable);
        $check = $t_hasher->CheckPassword($passwordToCheck, $hash);
        return $check;
    }


    /**
     * Creates a secure random string of "len" bytes of entropy, encoded as an
     * ASCII string.
     */
    public static function createSecretKey($len = 64) {
        $ret = base64_encode(openssl_random_pseudo_bytes($len));
        return $ret;
    }


    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}
