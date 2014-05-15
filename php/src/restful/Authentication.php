<?php

namespace WebRiffs;

require_once(__DIR__.'/Resource.php');

use Tonic;
use GroboAuth;
use Base;

/**
 * @uri /authentication/current
 */
class AuthenticationCurrent extends Resource {
    /**
     * Retrieve the current user information.  This is made 'post' to prevent
     * cross-site scripting attacks gaining more information than they need.
     *
     * @method POST
     * @authenticated
     */
    public function data() {
        $user =& $this->container['user'];
        return array(200, array(
            'username' => $user['Username'],
            'contact' => $user['Contact'],
            'is_admin' => $user['Is_Site_Admin'],
            'created_on' => $user['Created_On'],
            'last_updated_on' => $user['Last_Updated_On']
        ));
    }
}

/**
 * @uri /authentication/login
 */
class AuthenticationLogin extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }


    /**
     * @method POST
     */
    public function login() {
        $db =& $this->getDB();
        $data =& $this->getRequestData();
        if (! $data->{'username'} || ! $data->{'password'} ||
                ! $data->{'source'} ||
                ! is_string($data->{'username'}) ||
                ! is_string($data->{'password'}) ||
                ! is_string($data->{'source'})) {
            throw new Base\ValidationException(array(
                'data' => 'invalid request data'
            ));
        }
        
        $userData = AuthenticationLayer::login($db,
            $data->{'username'}, $this->getSourceId($data->{'source'}),
            $data->{'password'},
            // TODO make this configurable per source
            function($u, $p, $h) { return AuthenticationLayer::validatePassword($u, $p, $h); },
            
            $this->request->userAgent,
            $this->request->remoteAddr,
            null,
            // TODO make the session minutes settable by the user,
            // within reason
            Resource::DEFAULT_SESSION_TIMEOUT);
            
error_log("login returned");
print_r($userData);
        setcookie(Resource::COOKIE_NAME, $userData['Authentication_Challenge']);
        
        $userData['message'] = 'okay';
        
        return array(200, $userData);
    }
}


/**
 * @uri /authentication/logout
 */
class AuthenticationLogout extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }


    /**
     * @method POST
     * @authenticated
     */
    public function logout() {
        $db =& $this->getDB();
        $userAuth = $this->container['user'];
        AuthenticationLayer::logout($db, $userAuth['User_Id'],
            $userAuth['Ga_Session_Id']);
        
        // Some old browsers will only delete the cookie if you pass in an
        // old expiration date
        setcookie(Resource::COOKIE_NAME, $userAuth['Authentication_Challenge'],
            time() - 3600);
        
        return array(200, array('message' => 'okay'));
    }
}


/**
 * @uri /authentication/create
 */
class AuthenticationCreate extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }
    
    
    /**
     * @method PUT
     */
    public function createUser() {
        $db = $this->getDB();
        $data = $this->getRequestData();
        #die;
echo "username: ".$data->{'username'}." (false? ".(! $data->{'username'}).") (not is_string? ".! is_string($data->{'username'}).")\n";
echo "password: ".$data->{'password'}." (false? ".(! $data->{'password'}).") (not is_string? ".! is_string($data->{'password'}).")\n";
echo "contact: ".$data->{'contact'}." (false? ".(! $data->{'contact'}).") (not is_string? ".! is_string($data->{'contact'}).")\n";
echo "source: ".$data->{'source'}." (false? ".(! $data->{'source'}).") (not is_string? ".! is_string($data->{'source'}).")\n";
        if (! $data->{'username'} || ! $data->{'password'} ||
                ! $data->{'source'} || ! $data->{'contact'} ||
                ! is_string($data->{'username'}) ||
                ! is_string($data->{'password'}) ||
                ! is_string($data->{'contact'}) ||
                ! is_string($data->{'source'})) {
            var_dump($data);
            throw new Base\ValidationException(array(
                'json' => 'invalid request data'
            ));
        }
        
        // FIXME make this configurable per source
        $password = AuthenticationLayer::hashPassword($data->{'password'});
        
        $userId = AuthenticationLayer::createUser($db, $data->{'username'},
            $this->getSourceId($data->{'source'}), $data->{'username'},
            $password, $data->{'contact'}, false);
        
        // don't expose the internal ID to the user.
        return array(200, array(
            "message" => "User created successfully."
        ));
    }
}
