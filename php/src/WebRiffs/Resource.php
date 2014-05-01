<?php


namespace WebRiffs;

use Tonic;

class Resource extends Tonic\Resource
{
    /**
     * Validate that the given variable is a non-null number.
     */
    protected function validateId($id, $name) {
        if ($id == null || !is_int($id)) {
            // TODO include the id name in the error
            throw new Tonic\NotAcceptableException;
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



    protected function fetchSingleRow($statement) {
        $row = $statement->fetch();
        if (!$row) {
            throw new Tonic\NotFoundException;
        }
        $second = $statement->fetch();
        if (!$second) {
            return $row;
        }
        throw new Tonic\ConditionException;
    }


    function secure(string $role, int $minLevel) {
        $db = getDB();

        $auth = getUserIdentity($db);
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

}
