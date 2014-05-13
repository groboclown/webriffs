<?php


namespace WebRiffs;

use Tonic;

class Resource extends Tonic\Resource {
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
            $conn = new PDO($this->container['db_config']['dsn'],
                $this->container['db_config']['username'],
                $this->container['db_config']['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->container['dataStore'] = $conn;
            return $conn;
        } catch (Exception $e) {
            throw new Tonic\NotFoundException;
        }
    }
    
    
    protected function getSourceId($sourceName) {
        // For now, assume only one source.
        if (! $this->container['sources'][$sourceName] ||
                ! is_int($this->container['sources'][$sourceName]['id'])) {
            error_log("No registered source '".$sourceName."'");
            throw new Tonic\UnauthorizedException();
        }
        return validateId($this->container['sources'][$sourceName]['id'],
            "source");
    }
    
    
    /**
     * Ensures the request is authenticated, and stores the user authentication
     * data in the container['user'].
     */
    function authenticated() {
        if (! $_COOKIE[Resource::COOKIE_NAME]) {
            throw new Tonic\UnauthorizedException;
        }
        $cookie = $_COOKIE[Resource::COOKIE_NAME];
        
        $db =& getDB();
        $data =& AuthenticationLayer::getUserSession($db, $cookie,
            $this->request->userAgent, $this->request->remoteAddr,
            null,
            Resource::DEFAULT_SESSION_TIMEOUT);
        
        $this->container['user'] =& $data;
    }


    function secure(string $role, int $minLevel) {
        authenticated();
        $db =& getDB();

        $auth =& $this->container['user'];
        if (! isUserAuthSecureForRole($auth, $role) {
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


    function filmAuth($db, $userAuth, $filmVersionId, $roleSet) {
        if (!$userAuth) {
            throw new Tonic\UnauthorizedException;
        }
        $userid = $userAuth['user_id'];

        $args = array($filmVersionId, $userid);
        $query = 'SELECT COUNT(*) FROM FILM_AUTH WHERE Film_Version_id = ? AND User_Id = ? AND Role IN ('
            .implode(',', array_fill(1,count($roleSet),'?'))
            .')';
        $args = array_merge($args, $roleSet);

        $stmt = $db->($query);
        $stmt->execute($args);

        if ($stmt->fetchColumn() <= 0) {
            throw new Tonic\UnauthorizedException;
        }
    }

    
    
    
    const COOKIE_NAME = "WRAUTHCK";
    const DEFAULT_SESSION_TIMEOUT = 3 * 60;
}
