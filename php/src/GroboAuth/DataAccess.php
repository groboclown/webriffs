<?php

namespace GroboAuth;

use PBO;
use Base;
use Tonic;

// Requires "PasswordHash" for the hashing functions.
// Requires the dbo files for GroboAuth.

class DataAccess {

    /**
     * Creates a new GA user in the system.  Returns the id of the newly
     * created record.
     *
     * @returns the id
     */
    public static function createUser($db) {
        // FIXME figure out how to reference GA_USER instead
        $gauser =& GaUser::$INSTANCE;
        $data = $gauser->create($db, array());
        if (! $data) {
            // FIXME Log the error data
            throw new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem during user creation'
            ));
        }
        return intval($data['Ga_User_Id']);
    }

    /**
     * Administration function to create a new authentication source.  This
     * should only be called when absolutely required.  For performance
     * purposes, the system should store the source ID (returned by this
     * function) along with the source name (passed into this function)
     * in the source code.
     *
     * @returns the id
     */
    public static function createSource($db, $sourceName) {
        $gasource =& GaSource::$INSTANCE;
        try {
            $data = $gasource->create($db, array('Source_Name' => $sourceName));
        } catch (Exception $e) {
            throw new Base\ValidationException(array(
                'sourceName' => 'already exists'
            ));
        }
        if (! $data) {
            // FIXME Log the error data
            throw new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem during user creation'
            ));
        }
        return intval($data['Ga_Source_Id']);
    }


    /**
     * Sets the source information for a user.  If it is already assigned, it is
     * updated, otherwise it is created.
     *
     * The authentication code should be properly encoded by the callee
     * depending on the contents.  This method will NOT encrypt or hash the
     * value. IT IS UP TO THE CALLEE TO PERFORM PROPER ENCRYPTION OR HASHING.
     * This class provides helper methods to encrypt, decrypt, and one-way hash
     * values.
     *
     * This does not perform any validation on the username or authentication
     * code.  That should be done by the callee.
     *
     * Care should be taken when changing the username.  That may not be a valid
     * use case in all situations.
     *
     * @param userId (int) the GA_USER id
     * @param sourceId (int) the GA_SOURCE id
     * @param username the username for the source
     * @param authenticationCode the authentication code associated with the
     *     user name for this source.  If no code should be stored, then a
     *     blank string will be sufficient.
     * @returns the id of the user source
     */
    public static function setUserSource($db, $userId, $sourceId, $username,
            $authenticationCode) {
        $gausersource =& GaUserSource::$INSTANCE;
        $data = $gausersource->readAny($db,
            'SELECT Ga_User_Source_Id,Ga_User_Id,Ga_Source_Id,Username,' .
            'Authentication_Code FROM GA_USER_SOURCE WHERE Ga_User_Id = ? '.
            'AND Ga_Source_Id = ?',
            array($userId, $sourceId));
        if (! $data || sizeof($data) <= 0) {
            // create
            $data = $gausersource->create($db, array(
                'Ga_User_Id' => $userId,
                'Ga_Source_Id' => $sourceId,
                'Username' => $username,
                'Authentication_Code' => $authenticationCode
            ));
        } else {
            // update
            $data = $gausersource->update($db, array(
                'Ga_User_Source_Id' => $data['Ga_User_Source_Id'],
                'Ga_User_Id' => $userId,
                'Ga_Source_Id' => $sourceId,
                'Username' => $username,
                'Authentication_Code' => $authenticationCode
            ));
        }
        if (! $data) {
            // FIXME Log the error data
            throw new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem during user source creation'
            ));
        }
        return intval($data['Ga_User_Source_Id']);
    }
    
    
    /**
     * Returns an array containing 'Ga_User_Source_Id', 'Username',
     * 'Authentication_Code', or false if no such record exists.
     */
    public static function getUserSource($db, $userId, $sourceId) {
        $gausersource =& GaUserSource::$INSTANCE;
        
        $data = $gausersource->readByGa_User_Id_x_Ga_Source_Id(
            $db, $userId, $sourceId);
        if (! $data || sizeof($data) <= 0) {
            return false;
        }
        return array(
            'Ga_User_Id' => $data['Ga_User_Id'],
            'Ga_Source_Id' => $data['Ga_Source_Id'],
            'Ga_User_Source_Id' => $data[0]['Ga_User_Source_Id'],
            'Username' => $data[0]['Username'],
            'Authentication_Code' => $data[0]['Authentication_Code']
        );
    }
    
    
    public static function getUserSourceForUsername($db, $username, $sourceId) {
        $gausersource =& GaUserSource::$INSTANCE;
        
        $data = $gausersource->readByUsername_x_Ga_Source_Id(
            $db, $username, $sourceId);
        if (! $data || sizeof($data) <= 0) {
            return false;
        }
        return array(
            'Ga_User_Id' => $data['Ga_User_Id'],
            'Ga_Source_Id' => $data['Ga_Source_Id'],
            'Ga_User_Source_Id' => $data['Ga_User_Source_Id'],
            'Username' => $data['Username'],
            'Authentication_Code' => $data['Authentication_Code']
        );
    }
    
    
    public static function createPasswordRequest($db, $userSourceId,
            $expirationMinutes, $secretKey = NULL) {
        if (! $secretKey) {
            $secretKey = createSecretKey();
        }
        # FIXME the expires_on is now handled by the db layer
        $expUnix = time() + ($expirationMinutes * 60);
        $expStr = date('YYYY-MM-DD HH:MM:SS', $expUnix);
        
        $gapasswordrequest =& GaPasswordRequest::$INSTANCE;
        
        $data = $gapasswordrequest->create($db, array(
            'Ga_User_Source_Id' => $userSourceId,
            'Secret_Key' => $secretKey,
            'Was_Request_Handled' => 0,
            'Expires_On' => $expStr
        ));
        if (! $data || sizeof($data) <= 0) {
            return false;
        }
        return array(
            'Ga_Login_Attempt_Id' => $data['Ga_Login_Attempt_Id'],
            'Ga_User_Source_Id' => $userSourceId,
            'Secret_Key' => $secretKey,
            'Expires_On' => $expStr
        );
    }
    
    
    /**
     * Administration function to see all the active password requests.
     */
    public static function getAllActivePasswordRequests($db) {
        // FIXME
    }
    
    
    public static function getActivePasswordRequestsForSource($db, $sourceId) {
        // FIXME
    }
    
    
    public static function getActivePasswordRequestForUserSource(
            $db, $userSourceId) {
        // FIXME
    }
    
    
    public static function handlePasswordRequest($db, $passwordRequestId,
            $newAuthenticationCode) {
        // FIXME
    }
    
    
    public static function getPasswordChangeRequests($db, $userSourceId,
            $start, $end) {
        // FIXME
    }
    
    
    public static function recordLoginAttempt($db, $userSourceId,
            $wasSuccessful) {
        // FIXME
    }
    
    
    public static function getLoginAttemptsFor($db, $userSourceId,
            $mostRecentCount = -1) {
        // FIXME
        // limited paging support.  Pull in all the attempts (-1), or the
        // N most recent attempts, which means sorting in reverse order.
        // This allows checking if the M most recent attempts were invalid,
        // forcing a temporary login ban.
    }
    
    
    public static function countSessions($db, $userSourceId) {
        // FIXME
    }
    
    
    public static function getSessions($db, $userSourceId, $start, $end) {
        // FIXME
    }
    
    
    public static function expireSession($db, $sessionId) {
        // FIXME
    }
    
    
    public static function renewSession($db, $sessionId) {
        // FIXME
    }
    
    
    public static function createSession($db, $userSourceId, $userAgent,
            $remoteAddress, $forwardedFor, $authorizationChallenge,
            $expirationInMinutes) {
        // FIXME
    }
    
    
    
    
    
    
    
    
    
    // -----------------------------------------------------------------------
    // Helper Functions
    
    
    
    
    /**
     * Helper function to encrypt values.  "key" is the private password that
     * will encrypt the "string".
     *
     * THIS SHOULD NEVER BE USED FOR PASSWORDS.
     */
    public static function encrypt($key, $string) {
        $iv = mcrypt_create_iv(
            mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC),
            MCRYPT_DEV_URANDOM
        );
        
        $encrypted = ase64_encode(
            $iv .
            mcrypt_encrypt(
                MCRYPT_RIJNDAEL_256,
                hash('sha256', $key, true),
                $string,
                MCRYPT_MODE_CBC,
                $iv
            )
        );
        return $encrypted;
    }
    
    
    /**
     * Helper function to decrypt the values encrypted by "encrypt".
     */
    public static function decrypt($key, $encrypted) {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256,
            MCRYPT_MODE_CBC));

        $decrypted = rtrim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_256,
                hash('sha256', $key, true),
                substr($data, mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256,
                    MCRYPT_MODE_CBC)),
                MCRYPT_MODE_CBC,
                $iv
            ),
            "\0"
        );
        return $decrypted;
    }
    
    
    /**
     * Helper function to cryptographically hash a value.
     *
     * $iterations is an integer between 4 and 1000.  8 is a good number to use.
     * 
     */
    public static function hashPassword($password, $iterations,
            $portable = FALSE) {
        $t_hasher = new PasswordHash($iterations, $portable);
        $hash = $t_hasher->HashPassword($password);
        return $hash;
    }
    
    
    /**
     * @param $passwordToCheck the password passed in by the user, for
     *     authenticating against the hash.
     * @param $hash the value returned by hashPassword
     * @param $iterations Base-2 logarithm of the iteration count used for
     *     password stretching
     * @returns TRUE if valid, FALSE if not.
     */
    public static function checkPassword($passwordToCheck, $hash, $iterations,
            $portable = FALSE) {
        $t_hasher = new PasswordHash($iterations, $portable);
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
}
