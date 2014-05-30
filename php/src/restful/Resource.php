<?php


namespace WebRiffsRest;

use Tonic;
use PDO;
use Base;
use WebRiffs;

class Resource extends Base\Resource {
    /**
     * Validate that the given variable is a non-null number.
     */
    protected function validateId($id, $name) {
        if ($id == null || !is_int($id)) {
            // TODO include the id name in the error
            throw new Base\ValidationException(array(
                    $name => "not valid"
                ));
        }
        return $id;
    }


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
        // First, check if we've actually been authenticated.
        if (array_key_exists('authenticated', $this->container) &&
                $this->container['authenticated'] &&
                array_key_exists('user', $this->container)) {
            // already authenticated.  Don't run this again.
            return;
        }
        if (array_key_exists('authenticated', $this->container) &&
                ! $this->container['authenticated']) {
            // Already tried, and we aren't authenticated
            throw new Tonic\UnauthorizedException();
        }
        
        // Prevent extra DB hits on this request.
        $this->container['authenticated'] = false;
        
        if (! array_key_exists(Resource::COOKIE_NAME, $_COOKIE)) {
            throw new Tonic\UnauthorizedException;
        }
        $cookie = $_COOKIE[Resource::COOKIE_NAME];
        
        $db = $this->getDB();
        
        // This call will either return the session object, or throw an
        // exception.  It will never return null.
        $data = WebRiffs\AuthenticationLayer::getUserSession($db, $cookie,
            $this->request->userAgent, $this->request->remoteAddr,
            null,
            Resource::DEFAULT_SESSION_TIMEOUT);
        
        $this->container['user'] = $data;
        
        // This may not be needed, but it's a bit of extra protection for
        // calls back into this method.
        $this->container['authenticated'] = true;
        
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
    function secure(string $role, int $minLevel) {
        $this->authenticated();
        $db = $this->getDB();

        $auth =& $this->container['user'];
        if (! isUserAuthSecureForRole($auth, $role)) {
            throw new Tonic\UnauthorizedException();
        }
        return true;
    }
    
    
    /**
     * Tonic Annotation
     *
     * Requires that the header value "csrf-token" is set with a valid
     * CSRF token.
     *
     * The token should be passed to the client initially with a call to
     * createCsrfToken().
     */
    function csrf(string $action) {
        // CSRF tokens require authentication, so in case of an ordering issue,
        // trigger the authenticaiton to run first.
        $this->authenticated();
        
        // Check to see if the CSRF token was passed in.
        
        // FIXME may want to put this as a custom header instead of request
        // data.
        
        $token = $this->request->csrfToken;
        if (! $token) {
            throw new Tonic\UnauthorizedException();
        }
        $db = $this->getDB();
        $sessionId = $this->container['user']['Ga_Session_Id'];
        
        // "action" should be internal, so we're not checking its data
        
        if (! GroboAuth\DataAccess::validateCsrfToken($db,
                $sessionId, $token, $action)) {
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
    function createCsrfToken(string $action) {
        $this->authenticated();
        
        $db = $this->getDB();
        $sessionId = $this->container['user']['Ga_Session_Id'];
        return GroboAuth\DataAccess::createCsrfToken($db, $sessionId, $action);
    }


    function isUserAuthSecureForRole($userAuth, $role) {
        if (!$userAuth) {
            return false;
        }
        foreach (array_keys($userAuth['attributes']) as $key) {
            if (startsWith($key, 'role_') && $userAuth['attributes'][$key] == $role) {
                return true;
            }
        }
        return false;
    }
    
    
    
    const COOKIE_NAME = "WRAUTHCK";
    const DEFAULT_SESSION_TIMEOUT = 360;
}
