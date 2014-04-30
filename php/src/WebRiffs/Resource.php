<?php


namespace WebRiffs;

use Tonic;

class Resource extends Tonic\Resource
{
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


    function secure($role) {
        $db = getDB();

        $auth = isAuthorized($db);
        if (!$auth) {
            throw new Tonic\UnauthorizedException;
        }
        foreach (array_keys($auth['attributes']) as $key) {
            if (startsWith($key, 'role_') && $auth['attributes'][$key] == $role) {
                return true;
            }
        }
        throw new Tonic\UnauthorizedException;
    }


    function filmAuth($db, $filmVersionId, $roleSet) {
        $auth = isAuthorized($db, true);
        if (!$auth) {
            throw new Tonic\UnauthorizedException;
        }
        $userid = $auth['user_id'];

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
