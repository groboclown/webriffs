<?php

namespace WebRiffs;

use Tonic;


/**
 * @uri /user
 */
class UserCollection extends Tonic\Resource {
    /**
     * @method GET
     */
    public function list() {
        // FIXME
    }


    /**
     * @method POST
     */
    public function create() {
        // FIXME
    }
}


/**
 * High-level user queries.  Does not allow for exposure of secret information.
 *
 * @uri /user/:userid
 */
class User extends Tonic\Resource {
    /**
     * @method GET
     */
    public function display() {
        $userid = $this->userid;
        $db = getDB();
        $stmt = $db->('SELECT User_Id, Username, Last_Access FROM USER WHERE Username = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($userid));

        $userRow = fetchSingleRow($stmt);

        $ret = array(
            'username' => $userRow['Username'],
            'last_access' => $userRow['Last_Access']
        );

        $stmt = $db->('SELECT Attribute_Name, Attribute_Value FROM USER_ATTRIBUTE WHERE User_Id = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($userRow['User_Id']));

        if ($stmt) {
            while ($row = $stmt->fetch()) {
                if ($row['Attribute_Name'] == 'role') {
                    $ret['role'] = $row['Attribute_Value'];
                }
                // FIXME other attributes that any user can know, such as
                // ban expiration date.
            }
        }

        return new Tonic\Response(200, $ret);
    }


    /**
     * @method POST
     */
    public function update() {
        // FIXME ensure this is that user or an admin

        // FIXME update the data
    }


    /**
     * @method DELETE
     */
    public function remove() {
        // FIXME ensure this is that user or an admin

        // FIXME delete the data

        return new Tonic\Response(Tonic\Response::NOCONTENT);
    }
}
