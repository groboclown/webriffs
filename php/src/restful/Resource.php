<?php


namespace WebRiffsRest;

use Tonic;
use PDO;
use Base;
use WebRiffs;
use GroboAuth;

class Resource extends Base\Resource {


    protected function getDB() {
        if ($this->container['dataStore']) {
            return $this->container['dataStore'];
        }
        try {
            $conn = new PDO($this->container['db_config-dsn'],
                $this->container['db_config-username'],
                $this->container['db_config-password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->container['dataStore'] = $conn;
            return $conn;
        } catch (Exception $e) {
            //throw new Tonic\NotFoundException;
            throw $e;
        }
    }
    
    
    protected function getSourceId($sourceName) {
        // For now, assume only one source.
        if (! $this->container['sources'][$sourceName] ||
                ! is_int($this->container['sources'][$sourceName]['id'])) {
            error_log("No registered source '".$sourceName."'");
            throw new Tonic\UnauthorizedException();
        }
        return $this->validateId($this->container['sources'][$sourceName]['id'],
            "source");
    }
    
    
    /**
     * Tonic Annotation
     *
     * Ensures the request is authenticated, and stores the user authentication
     * data in the container['user'].
     */
    function authenticated() {
        if (! $this->isUserAuthenticated()) {
            //error_log("User not authenticated; ".print_r($this->request, true)." cookie ".$_COOKIE[Resource::COOKIE_NAME]);
            throw new Tonic\UnauthorizedException();
        }
        return true;
    }

    /**
     * Tonic Annotation
     *
     * @param string $role
     * @param int $minLevel
     * @throws Tonic\UnauthorizedException
     * @return boolean
     */
    function secure($role, $minLevel) {
        $this->authenticated();
        $minLevel = intval($minLevel);
        $db = $this->getDB();

        $auth = $this->getUserSessionAuthorization();
        
        if (! $this->isUserAuthSecureForRole($auth, $role, $minLevel)) {
            throw new Tonic\UnauthorizedException();
        }
        return true;
    }
    
    
    function getUserSessionAuthorization() {
        $this->authenticated();
        $userInfo = $this->container['user'];
        if (! array_key_exists('authorization', $userInfo)) {
            $auth = WebRiffs\AuthenticationLayer::getUserAccess(
                $this->getDB(), $userInfo['User_Id']);
            $userInfo['authorization'] = $auth;
        }
        return $userInfo['authorization'];
    }
    
    
    /**
     * Tonic Annotation
     *
     * Requires that the header value "x-csrf-token" is set with a valid
     * CSRF token.
     *
     * The token should be passed to the client initially with a call to
     * createCsrfToken().
     */
    function csrf($action) {
        // CSRF tokens require authentication, so in case of an ordering issue,
        // trigger the authenticaiton to run first.
        $this->authenticated();
        
        // Check to see if the CSRF token was passed in (it is passed as a
        // header key/value).
        
        $token = $this->request->xCsrfToken;
        if (! $token) {
            error_log("Request for action ".$action." with no token; ".
                    print_r($this->request, true));
            throw new Tonic\UnauthorizedException();
        }
        $db = $this->getDB();
        $sessionId = $this->container['user']['Ga_Session_Id'];
        
        // "action" should be internal, so we're not checking its data
        
        if (! GroboAuth\DataAccess::validateCsrfToken($db,
                $sessionId, $token, $action)) {
            error_log("Invalid token ".$token." for action ".$action." in session ".$sessionId);
            throw new Tonic\UnauthorizedException();
        }
        
        return true;
    }
    
    
    /**
     * Create a CSRF token for an action, that can be validated with the
     * "@csrf <action>" Tonic annotation.  This should be sent back from the
     * client in the JSON field '_csrf'.
     *
     * Only use this for requests that use non-GET methods.
     *
     * @param string $action
     */
    function createCsrfToken($action) {
        $this->authenticated();
        
        $db = $this->getDB();
        $sessionId = $this->container['user']['Ga_Session_Id'];
        
        // FIXME TEST
        //throw new Exception("ensure failures are checked");
        
        return GroboAuth\DataAccess::createCsrfToken($db, $sessionId, $action);
    }


    function isUserAuthSecureForRole($userAuth, $role, $minPrivilege) {
        if (!$userAuth) {
            return false;
        }
        return (array_key_exists($role, $userAuth) &&
                $userAuth[$role] >= $minPrivilege);
    }


    /**
     * Checks if the request is authenticated, and returns true or false.
     * Also, the <code>$this->container['user']</code> will be set to the
     * user data associative array.
     *
     * $this->container['user'] contains:
     *
     *  'Ga_Session_Id'
     *  'Ga_User_Id'
     *  'Ga_Source_Id'
     *  'Login_Attempts'
     *  'Authentication_Challenge'
     *
     *  'User_Id'
     *  'Username'
     *  'Contact'
     *  'Is_Admin'
     *  'Created_On'
     *  'Last_Updated_On'
     */
    function isUserAuthenticated() {
        // First, check if we've already been authenticated.  This avoids an
        // extra db hit.
        if (array_key_exists('authenticated', $this->container) &&
                $this->container['authenticated'] &&
                array_key_exists('user', $this->container)) {
            // already authenticated.  Don't run this again.
            return true;
        }
        if (array_key_exists('authenticated', $this->container) &&
                ! $this->container['authenticated']) {
            return false;
        }
    
        // Since we haven't been authenticated yet, default to "false" in case
        // an exception occurs.
        $this->container['authenticated'] = false;
    
        if (! array_key_exists(Resource::COOKIE_NAME, $_COOKIE)) {
            // No authentication cookie was sent.
            return false;
        }
        $cookie = $_COOKIE[Resource::COOKIE_NAME];
    
        $db = $this->getDB();
    
        // This call will either return the session object or false.
        $data = WebRiffs\AuthenticationLayer::getUserSession($db, $cookie,
                $this->request->userAgent, $this->request->remoteAddr,
                null,
                Resource::DEFAULT_SESSION_TIMEOUT);
        if (! $data) {
            // Resetting the cookie should be done in Authentication, but
            // here it is.
            // We have an invalid cookie.  Clear it out.  Something else may
            // reset it, though.
            setcookie(Resource::COOKIE_NAME,
                $cookie, time() - 3600,
                $this->container['path'], '', false, true);
            return false;
        }
    
        $this->container['user'] = $data;
    
        // This may not be needed, but it's a bit of extra protection for
        // calls back into this method.
        $this->container['authenticated'] = true;
    
        return true;
    }
    
    
    
    const COOKIE_NAME = "WRAUTHCK";
    const DEFAULT_SESSION_TIMEOUT = 360;
}
