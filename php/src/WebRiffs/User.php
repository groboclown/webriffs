<?php

namespace WebRiffs;

use Tonic;

/**
 * High-level user queries.  Does not allow for exposure of secret information.
 *
 * @uri /user/:user
 */
class User extends Tonic\Resource {
    /**
     * @method get
     * @provides application/json
     * @json
     */
    public function details($user) {
        $db = getDB();
        $stmt = $db->('SELECT User_Id, Username, Last_Access FROM USER WHERE Username = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($user));

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
}
