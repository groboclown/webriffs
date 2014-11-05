<?php

namespace WebRiffsRest;

require_once(__DIR__.'/Resource.php');

use Tonic;
use WebRiffs;
use Base;


/**
 * @uri /user
 */
class UserCollection extends Resource {
    /**
     * @method GET
     * @csrf read_users
     */
    public function fetch() {
        $this->secure(WebRiffs\Access::$ADMIN_USER_VIEW,
                WebRiffs\Access::$PRIVILEGE_ADMIN);
        
        $db = $this->getDB();
        $result = WebRiffs\AdminLayer::pageUsers($db);
        
        return array(200, $result);
    }
}


/**
 * High-level user queries.  Does not allow for exposure of secret information.
 *
 * @uri /user/:userid
 */
class UserObj extends Resource {
    /**
     * For this particular method only, we fudge a bit and let the userid be
     * the user name.
     *
     * @method GET
     */
    public function display() {
        $userid = $this->userid;
        $db = $this->getDB();
        
        // FIXME
        
        //$stmt = $db->prepare('SELECT User_Id, Username, Email, Authentication_Source, Created_On, Last_Updated_On, Last_Access FROM USER WHERE Username = ? OR User_Id = ?');
        //$stmt->setFetchMode(PDO::FETCH_ASSOC);
        //$stmt->execute(array($userid, $userid));
        //
        //$userRow = fetchSingleRow($stmt);
        //$userid = $userRow['User_Id'];
        //
        //$ret = array(
        //    'User_Id' => $userid,
        //    'Username' => $userRow['Username'],
        //    'Last_Access' => $userRow['Last_Access'],
        //    'attributes' => array()
        //);
        //
        //$canSeePrivate = false;
        //
        //// Determine if the current user is the requesting user or admin, and if
        //// so, return more information than usual.
        //$auth = getUserIdentity($db, true);
        //if (isUserOrAdmin($userid, $auth)) {
        //    $canSeePrivate = true;
        //
        //    $ret['Email'] = $userRow['Email'];
        //    $ret['Authentication_Source'] = $userRow['Authentication_Source'];
        //    $ret['Created_On'] = $userRow['Created_On'];
        //    $ret['Last_Updated_On'] = $userRow['Last_Updated_On'];
        //}
        //
        //$stmt = $db->prepare('SELECT Attribute_Name, Attribute_Value FROM USER_ATTRIBUTE WHERE User_Id = ?');
        //$stmt->setFetchMode(PDO::FETCH_ASSOC);
        //$stmt->execute(array($userRow['User_Id']));
        //
        //if ($stmt) {
        //    while ($row = $stmt->fetch()) {
        //        if (startsWith('role_', $row['Attribute_Name'])
        //
        //            // FIXME or other attributes that any user can know, such as
        //            // ban expiration date.
        //
        //            || $canSeePrivate
        //            ) {
        //            $ret['attributes'][$row['Attribute_Name']] = $row['Attribute_Value'];
        //        }
        //    }
        //}
        
        $ret = array();

        //return new Tonic\Response(200, $ret);

        return array(500, array('message' => "not implemented yet"));
    }


    /**
     * @method POST
     */
    public function update() {
        $userid = $this->userid;
        $db = $this->getDB();

        $auth = getUserIdentity($db);

        // ensure this is that user or an admin
        if (! isUserOrAdmin($userid, $auth)) {
            throw new Tonic\UnauthorizedException;
        }

        $data = $this->request->data;

        // FIXME update the data
        //$attributes =

        return array(500, array('message' => "not implemented yet"));
    }


    /**
     * @method DELETE
     */
    public function remove() {
        // FIXME ensure this is that user or an admin

        // FIXME delete the data

        //return new Tonic\Response(Tonic\Response::NOCONTENT);

        return array(500, array('message' => "not implemented yet"));
    }



    private function isUserOrAdmin($userid, $userAuth) {
        // Determine if the current user is the requesting user or admin, and if
        // so, return more information than usual.
        return ($userAuth['user_id'] == $userid || isUserAuthSecureForRole($userAuth, 'admin'));
    }
}
