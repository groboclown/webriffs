<?php


namespace WebRiffs;

use Tonic;
use PDO;
use Base;

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
     * Ensures the request is authenticated, and stores the user authentication
     * data in the container['user'].
     */
    function authenticated() {
        // FIXME this line causes a warning if the cookie isn't in the
        // request.  Need to find the correct way to check if the key exists.
        if (! array_key_exists(Resource::COOKIE_NAME, $_COOKIE)) {
            throw new Tonic\UnauthorizedException;
        }
        $cookie = $_COOKIE[Resource::COOKIE_NAME];
        
        $db = $this->getDB();
        $data = AuthenticationLayer::getUserSession($db, $cookie,
            $this->request->userAgent, $this->request->remoteAddr,
            null,
            Resource::DEFAULT_SESSION_TIMEOUT);
        
        $this->container['user'] = $data;
    }


    function secure(string $role, int $minLevel) {
        $this->authenticated();
        $db =& $this->getDB();

        $auth =& $this->container['user'];
        if (! isUserAuthSecureForRole($auth, $role)) {
            throw new Tonic\UnauthorizedException;
        }
        return true;
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
