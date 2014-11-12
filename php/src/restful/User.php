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
        $result = WebRiffs\UserLayer::pageUsers($db);
        
        return array(200, $result);
    }
}


/**
 * High-level user queries.  Does not allow for exposure of secret information.
 *
 * @uri /user/:username
 */
class UserObj extends Resource {
    /**
     *
     * @method GET
     */
    public function display() {
        $username = $this->username;
        $db = $this->getDB();
        
        
        
        // Let's find out who's making the request.
        // Admin user: can see everything except password
        // Current user: view all details except admin
        // Logged in user: view bare minimum - user name, join date, some
        //    public information
        // Guest user: user name
        
        $access = 1;
            
        
        // Let's discover the user requested.
        $data = WebRiffs\UserLayer::loadUser($db, $username, $access);
        if ($data == false) {
            // no such user
            return array(204, array());
        }
                
        $ret = array();

        //return new Tonic\Response(200, $ret);

        return array(500, array('message' => "not implemented yet"));
    }


    /**
     * @method POST
     * @csrf update_user
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
     * @csrf remove_user
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
