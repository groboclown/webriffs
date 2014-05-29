<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;
use GroboAuth;


/**
 * Business logic on top of the database API.
 */
class AuthenticationLayer {


    /**
     * Creates a new user.
     * The authentication code should already be encrypted,
     * if necessary, based on the sourceId requirements.
     */
    public static function createUser($db, $username, $sourceId, $sourceUser,
        $authenticationCode, $contact, $isAdmin) {
        if (!AuthenticationLayer::isValidUsername($username)) {
            throw new Base\ValidationException(
                array(
                    'username' => 'invalid username'
                ));
        }
        if (!AuthenticationLayer::isValidContact($contact)) {
            throw new Base\ValidationException(
                array(
                    'contact' => 'invalid contact information'
                ));
        }
        
        if (!!$isAdmin) {
            $isAdmin = 1;
        } else {
            $isAdmin = 0;
        }
        
        // First check - does the user already exist?  This is a bit superfluous,
        // as the user table create will do that check implicitly, but this
        // first line of defence will protect our code from inadvertently
        // creating a new ga_user record.
        

        $data = User::$INSTANCE->countBy_Username($db, $username);
        AuthenticationLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'username' => 'username already exists'
                )));
        $rowcount = $data['result'];
        if ($rowcount > 0) {
            throw new Base\ValidationException(
                array(
                    'username' => 'username already exists'
                ));
        }
        
        $gaUserId = GroboAuth\DataAccess::createUser($db);
        $gaUserSourceId = GroboAuth\DataAccess::setUserSource($db, $gaUserId,
            $sourceId, $sourceUser, $authenticationCode);
        
        $data = User::$INSTANCE->create($db, $username, $contact, $gaUserId,
            $sourceId, $isAdmin);
        AuthenticationLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'username' => 'problem creating the user'
                )));
        $userId = $data['result'];
        return $userId;
    }


    /**
     * If the login was successful, returns a user object if so.
     * Will throw
     * an exception if it was unsuccessful.
     *
     * $authenticationCheckFunction takes 3 arguments: the username for the
     * user's source, the login authentication code, and the stored
     * authentication code.
     */
    public static function login($db, $username, $sourceId, $authenticationCode,
        $authenticationCheckFunction, $User_Agent, $Remote_Address,
        $Forwarded_For, $sessionRenewalMinutes) {
        //error_log("checking login for [".$username."] [".$sourceId."] [".$authenticationCode."] [".$User_Agent."]");
        if (!AuthenticationLayer::isValidUsername($username)) {
            throw new Base\ValidationException(
                array(
                    'username' => 'invalid username'
                ));
        }
        
        // We allow these to be null, but we can't insert a null.
        if (!$Remote_Address || !is_string($Remote_Address)) {
            $Remote_Address = "";
        }
        if (!$Forwarded_For || !is_string($Forwarded_For)) {
            $Forwarded_For = "";
        }
        
        $data = User::$INSTANCE->readBy_Username($db, $username);
        AuthenticationLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'username' => 'problem accessing users'
                )));
        if (sizeof($data['result']) != 1) {
            // FIXME add in a bit of slowdown by performing a needless password
            // check.
            

            throw new Base\ValidationException(
                array(
                    // DO NOT let the caller know that the user doesn't exist.
                    'authentication' => 'authentication failed'
                ));
        }
        $userData = $data['result'][0];
        $userId = intval($userData['User_Id']);
        $gaUserId = intval($userData['Ga_User_Id']);
        $contact = $userData['Contact'];
        $isAdmin = intval($userData['Is_Site_Admin']) == 0 ? false : true;
        $createdOn = $userData['Created_On'];
        $lastUpdatedOn = $userData['Last_Updated_On'];
        error_log(
            "pulled in data [" . $userId . "] [" . $gaUserId . "] [" . $contact .
                 "] [" . $isAdmin . "]");
        
        $userSourceData = GroboAuth\DataAccess::getUserSource($db, $gaUserId,
            $sourceId);
        if (!$userSourceData) {
            throw new Base\ValidationException(
                array(
                    // DO NOT let the caller know that the user-source pair aren't
                    // registered
                    'authentication' => 'authentication failed'
                ));
        }
        
        $userSourceId = intval($userSourceData['Ga_User_Source_Id']);
        error_log("found user source " . $userSourceId);
        
        $loginValid = $authenticationCheckFunction($userSourceData['Username'],
            $authenticationCode, $userSourceData['Authentication_Code']);
        error_log("found validation result: " . $loginValid);
        
        // FIXME limitation in the code: we can only record login attempts
        // for valid user/source pairs.  If someone is brute forcing the system,
        // we won't record it.
        

        GroboAuth\DataAccess::recordLoginAttempt($db, $userSourceId,
            $User_Agent, $Remote_Address, $Forwarded_For,
            (!!$loginValid) ? 1 : 0);
        
        // FIXME check for login attempts to see if the user is locked out
        

        // FIXME check if the user is banned
        

        if (!$loginValid) {
            throw new Base\ValidationException(
                array(
                    // DO NOT let the caller know that the password was wrong.
                    'authentication' => 'authentication failed'
                ));
        }
        
        // session information
        do {
            $authenticationChallenge = GroboAuth\DataAccess::createSecretKey();
            //error_log("Generated auth key ".$authenticationChallenge);
            

            $sessionId = GroboAuth\DataAccess::createSession($db, $userSourceId,
                $User_Agent, $Remote_Address, $Forwarded_For,
                $authenticationChallenge, $sessionRenewalMinutes);
            //error_log("Session id is ".$sessionId);
        } while ($sessionId === false);
        error_log("Session id is " . $sessionId);
        
        return array(
            'User_Id' => $userId,
            'Ga_User_Source_Id' => $userSourceId,
            'Ga_User_Id' => $gaUserId,
            'Contact' => $contact,
            'Is_Admin' => ($isAdmin ? true : false),
            'Created_On' => $createdOn,
            'Last_Updated_On' => $lastUpdatedOn,
            'Ga_Session_Id' => $sessionId,
            'Authentication_Challenge' => $authenticationChallenge
        );
    }


    /**
     * Returns the current user session.
     * If the user is not logged in, then
     * an UnauthorizedException is thrown.
     */
    public static function getUserSession($db, $authenticationChallenge,
        $User_Agent, $Remote_Address, $Forwarded_For, $sessionRenewalMinutes) {
        
        // We allow these to be null, but we can't insert a null.
        if (!$Remote_Address || !is_string($Remote_Address)) {
            $Remote_Address = "";
        }
        if (!$Forwarded_For || !is_string($Forwarded_For)) {
            $Forwarded_For = "";
        }
        
        $retData = GroboAuth\DataAccess::getUserForSession($db, $User_Agent,
            $Remote_Address, $Forwarded_For, $authenticationChallenge,
            $sessionRenewalMinutes, 10);
        if ($retData === false) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = User::$INSTANCE->readBy_Ga_User_Id($db, $retData['Ga_User_Id']);
        AuthenticationLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'username' => 'problem accessing users'
                )));
        if (sizeof($data['result']) != 1) {
            // TODO should remove the GroboAuth data corresponding to this
            // user session.
            

            throw new Tonic\UnauthorizedException();
        }
        $userData = $data['result'][0];
        $retData['User_Id'] = intval($userData['User_Id']);
        $retData['Username'] = $userData['Username'];
        $retData['Contact'] = $userData['Contact'];
        $retData['Is_Admin'] = (intval($userData['Is_Site_Admin']) == 0 ? false : true);
        $retData['Ga_User_Id'] = intval($userData['Ga_User_Id']);
        $retData['Created_On'] = $userData['Created_On'];
        $retData['Last_Updated_On'] = $userData['Last_Updated_On'];
        
        // FIXME pull in the other user authentication data.
        

        return $retData;
    }


    public static function logout($db, $userId, $sessionId) {
        GroboAuth\DataAccess::logoutSession($db, $sessionId);
    }
    

    // ----------------------------------------------------------------------
    // Validation Methods
    


    /**
     * Check if the username contains any undesired characters.
     *
     * Rule: can only contain the characters a-z, A-Z, 0-9, _, and -. The
     * name can be at most 64 characters long.
     */
    public static function isValidUsername($username) {
        if (!$username or !is_string($username)) {
            return false;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $username)) {
            return false;
        }
        
        return true;
    }


    /**
     * Checks if the contact meets the minimum requirements for a
     * contact value.
     * The contact should be a valid email address.
     */
    public static function isValidContact($contact) {
        if (!$contact || strlen($contact) > 2048) {
            return false;
        }
        
        // ensure proper email address
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        return true;
    }
    

    // ----------------------------------------------------------------------
    // Utility Functions
    


    /**
     * Usable as an input to the login method.
     * The hashed password comes from
     * the function "hashPassword".
     */
    public static function validatePassword($username, $password, $hashed) {
        return GroboAuth\DataAccess::checkPassword($password, $hashed, 10,
            false);
    }


    public static function hashPassword($password) {
        return GroboAuth\DataAccess::hashPassword($password, 10, false);
    }
    

    // ----------------------------------------------------------------------
    // Private Functions
    private static function checkError($returned, $exception) {
        if ($returned["haserror"]) {
            $backtrace = 'Database access error (' . $returned["errorcode"] . ' ' .
                 $returned["error"] . '):';
            foreach (debug_backtrace() as $stack) {
                $backtrace .= '\n    ' . $stack['function'] . '(' .
                     implode(', ', $stack['args']) . ') [' . $stack['file'] .
                     ' @ ' . $stack['line'] . ']';
            }
            error_log($backtrace);
            
            // TODO make the error messages language agnostic.
            

            // can have special logic for the $errorSource->errnos
            // error codes, to have friendlier messages.
            

            // 1062: already in use.
            

            throw $exception;
        }
    }
}