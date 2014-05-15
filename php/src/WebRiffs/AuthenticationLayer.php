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
     * Creates a new user.  The authentication code should already be encrypted,
     * if necessary, based on the sourceId requirements.
     */
    public static function createUser($db, $username, $sourceId, $sourceUser,
            $authenticationCode, $contact, $isAdmin) {
        
        if (! AuthenticationLayer::isValidUsername($username)) {
            throw new Base\ValidationException(array(
                    'username' => 'invalid username'
                ));
        }
        if (! AuthenticationLayer::isValidContact($contact)) {
            throw new Base\ValidationException(array(
                    'contact' => 'invalid contact information'
                ));
        }
        
        if (!! $isAdmin) {
            $isAdmin = 1;
        } else {
            $isAdmin = 0;
        }
        
        // First check - does the user already exist?  This is a bit superfluous,
        // as the user table create will do that check implicitly, but this
        // first line of defence will protect our code from inadvertently
        // creating a new ga_user record.
        
        $rowcount = User::$INSTANCE->countBy_Username($username);
        checkError(User::$INSTANCE, new Base\ValidationException(array(
                'username' => 'username already exists'
            )));
        if ($rowcount > 0) {
            throw new Base\ValidationException(array(
                'username' => 'username already exists'
            ));
        }
        
        $gaUserId = GroboAuth\DataAccess::createUser($db);
        $gaUserSourceId = GroboAuth\DataAccess::setUserSource($gaUserId,
            $sourceId, $sourceUser, $authenticationCode);
        
        $userId = intval(User::$INSTANCE->create($db, $username, $contact,
            $gaUserId, $isAdmin));
        return $userId;
    }
    
    
    /**
     * If the login was successful, returns a user object if so.  Will throw
     * an exception if it was unsuccessful.
     *
     * $authenticationCheckFunction takes 3 arguments: the username for the
     * user's source, the login authentication code, and the stored
     * authentication code.
     */
    public static function login($db, $username, $sourceId,
            $authenticationCode, $authenticationCheckFunction, $User_Agent,
            $Remote_Address, $Forwarded_For, $sessionRenewalMinutes) {
        
        if (! AuthenticationLayer::isValidUsername($username)) {
            throw new Base\ValidationException(array(
                    'username' => 'invalid username'
                ));
        }
        
        // FIXME strip the User_Agent and Forwarded_For down to within the
        // size limits.  And Remote_Address
        
        $userData = User::$INSTANCE->readBy_Username($db, $username);
        checkError(User::$INSTANCE, new Base\ValidationException(array(
                'username' => 'problem accessing users'
            )));
        if (sizeof($userData) != 1) {
            throw new Base\ValidationException(array(
                // DO NOT let the caller know that the user doesn't exist.
                'authentication' => 'authentication failed'
            ));
        }
        $userId = intval($userData[0]['User_Id']);
        $gaUserId = intval($userData[0]['Ga_User_Id']);
        $contact = $userData[0]['Contact'];
        $isAdmin = intval($userData[0]['Is_Site_Admin']);
        $createdOn = $userData[0]['Created_On'];
        $lastUpdatedOn = $userData[0]['Last_Updated_On'];
        
        $userSourceData = GroboAuth\DataAccess::getUserSource($db, $gaUserId,
            $sourceId);
        if (! $userSourceData) {
            throw new Base\ValidationException(array(
                // DO NOT let the caller know that the user-source pair aren't
                // registered
                'authentication' => 'authentication failed'
            ));
        }
        
        $userSourceId = intval($userSourceData['Ga_User_Source_Id']);
        
        $loginValid = $authenticationCheckFunction(
            $userSourceData['Username'],
            $authenticationCode,
            $userSourceData['Authentication_Code']);
        
        // FIXME limitation in the code: we can only record login attempts
        // for valid user/source pairs.  If someone is brute forcing the system,
        // we won't record it.
        
        GroboAuth\DataAccess::recordLoginAttempt($db, $userSourceId,
            $User_Agent, $Remote_Address, $Forwarded_For,
            (!! $loginValid) ? 1 : 0);
        
        // FIXME check for login attempts to see if the user is locked out
        
        // FIXME check if the user is banned
        
        if (! $loginValid) {
            throw new Base\ValidationException(array(
                // DO NOT let the caller know that the password was wrong.
                'authentication' => 'authentication failed'
            ));
        }
        
        // session information
        do {
            $authenticationChallenge = GroboAuth\DataAccess::createSecretKey();
        
            $sessionId = GroboAuth\DataAccess::createSession($db, $userSourceId,
                $User_Agent, $Remote_Address, $Forwarded_For,
                $authorizationChallenge, $sessionRenewalMinutes);
        } while ($sessionId === false);
        
        return array(
            'User_Id' => $userId,
            'Ga_User_Source_Id' => $userSourceId,
            'Ga_User_Id' => $gaUserId,
            'Contact' => $contact,
            'Is_Admin' => ($isAdmin ? true : false),
            'Created_On' => $createdOn,
            'Last_Updated_On' => $lastUpdatedOn,
            'Ga_Session_Id' => $sessionId,
            'Authentication_Challenge' => $authenticationChallenge,
            );
    }
    
    
    /**
     * Returns the current user session.  If the user is not logged in, then
     * an UnauthorizedException is thrown.
     */
    public static function getUserSession($db, $authenticationChallenge,
            $User_Agent, $Remote_Address, $Forwarded_For,
            $sessionRenewalMinutes) {
        $data = GroboAuth\DataAccess::getUserForSession($db,
            $User_Agent, $Remote_Address, $Forwarded_For,
            $sessionRenewalMinutes);
        if ($data === false) {
            throw new Tonic\UnauthorizedException();
        }
        
        $userData = User::$INSTANCE->readBy_Ga_User_Id($db,
            $data['Ga_User_Id']);
        checkError(User::$INSTANCE, new Base\ValidationException(array(
                'username' => 'problem accessing users'
            )));
        if (sizeof($userData) != 1) {
            // TODO should remove the GroboAuth data corresponding to this
            // user session.
            
            throw new Tonic\UnauthorizedException();
        }
        
        $data['User_Id'] = intval($userData[0]['User_Id']);
        $data['Username'] = $userData[0]['Username'];
        $data['Contact'] = $userData[0]['Contact'];
        $data['Is_Admin'] = (intval($userData[0]['Is_Site_Admin']) == 1 ? true : false);
        $data['Created_On'] = $userData[0]['Created_On'];
        $data['Last_Updated_On'] = $userData[0]['Last_Updated_On'];
        
        // FIXME pull in the other user authentication data.
        
        return $data;
    }
    
    
    public static function logout($db, $userId, $sessionId) {
        GroboAuth\DataAccess::logoutSession($db, $sessionId);
    }
    
    
    
    
    
    
    // ----------------------------------------------------------------------
    // Validation Methods
    
    
    /**
     * Check if the username contains any undesired characters.
     *
     * Rule: can only contain the characters a-z, A-Z, 0-9, _, and -.  The
     * name can be at most 64 characters long.
     */
    public static function isValidUsername($username) {
        if (! $username or ! is_string($username)) {
            return false;
        }
        
        if (! preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $username)) {
            return false;
        }
        
        return true;
    }
    

    /**
     * Checks if the contact meets the minimum requirements for a
     * contact value.  The contact should be a valid email address.
     */
    public static function isValidContact($contact) {
        if (! $contact || strlen($contact) > 2048) {
            return false;
        }
        
        // ensure proper email address
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        return true;
    }
    
    
    
    // ----------------------------------------------------------------------
    // Utility Functions
    
    
    /**
     * Usable as an input to the login method.  The hashed password comes from
     * the function "hashPassword".
     */
    public static function validatePassword($username, $password, $hashed) {
        return GroboAuth\DataAccess::checkPassword($password, $hashed, 10, false);
    }
    
    
    public static function hashPassword($password) {
        return GroboAuth\DataAccess::hashPassword($password, 10, false);
    }
    
    
    
    // ----------------------------------------------------------------------
    // Private Functions
    
    
    private static function checkError($errorSource, $exception) {
        if (sizeof($errorSource->errors) > 0) {
            $backtrace = 'Database access error (['.
                implode('], [', $errorSource->errors).']):';
            foreach (debug_backtrace() as $stack) {
                $backtrace .= '\n    '.$stack['function'].'('.
                    implode(', ', $stack['args']).') ['.
                    $stack['file'].' @ '.$stack['line'].']';
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