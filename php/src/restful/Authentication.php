<?php

namespace WebRiffsRest;

require_once (__DIR__ . '/Resource.php');

use Tonic;
use GroboAuth;
use Base;
use WebRiffs;


/**
 * @uri /authentication/current
 */
class AuthenticationCurrent extends Resource {


    /**
     * Retrieve the current user information.
     *
     * This does not use the CSRF token.
     *
     * @authenticated
     * @method POST
     */
    public function data() {
        $user = $this->container['user'];
        return array(
            200,
            array(
                'username' => $user['Username'],
                'contact' => $user['Contact'],
                'is_admin' => $user['Is_Admin'],
                'created_on' => $user['Created_On'],
                'last_updated_on' => $user['Last_Updated_On']
            )
        );
    }
}


/**
 * Requests a CSRF token for an action.  The user must be logged in to access
 * the tokens.
 *
 * @uri /authentication/token/:actionname
 */
class AuthenticationToken extends Resource {
    /**
     * @authenticated
     * @method GET
     */
    public function data() {
        $action = $this->actionname;
        $user = $this->container['user'];
        $sessionId = $user['Ga_Source_Id'];
        $db = $this->getDB();
        $token = GroboAuth\DataAccess::createCsrfToken($db, $sessionId,
                $action);
        
        return array(
            200,
            array('csrf' => $token)
        );
    }
}


/**
 * @uri /authentication/login
 */
class AuthenticationLogin extends Resource {


    /**
     *
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }


    /**
     *
     * @method POST
     */
    public function login() {
        $db = $this->getDB();
        $data = $this->getRequestData();
        if (!$data->{'username'} || !$data->{'password'} || !$data->{'source'} ||
             !is_string($data->{'username'}) || !is_string($data->{'password'}) ||
             !is_string($data->{'source'})) {
            throw new Base\ValidationException(
                array(
                    'data' => 'invalid request data'
                ));
        }
        
        $userData = WebRiffs\AuthenticationLayer::login($db,
            $data->{'username'}, $this->getSourceId($data->{'source'}),
            $data->{'password'},
            // TODO make this configurable per source
            function ($u, $p, $h) {
                return WebRiffs\AuthenticationLayer::validatePassword(
                        $u, $p, $h);
            },

            $this->request->userAgent, $this->request->remoteAddr, null,
            // TODO make the session minutes settable by the user,
            // within reason
            Resource::DEFAULT_SESSION_TIMEOUT);
        
        setcookie(Resource::COOKIE_NAME, $userData['Authentication_Challenge']);
        
        $userData['message'] = 'okay';
        
        return array(
            200,
            $userData
        );
    }
}


/**
 * @uri /authentication/logout
 */
class AuthenticationLogout extends Resource {


    /**
     *
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }


    /**
     *
     * @csrf logout
     * @method POST
     */
    public function logout() {
        $db = $this->getDB();
        $userAuth = $this->container['user'];
        //error_log('User auth value: '.print_r($userAuth, true));
        WebRiffs\AuthenticationLayer::logout($db, $userAuth['User_Id'],
            $userAuth['Ga_Session_Id']);
        
        // Some old browsers will only delete the cookie if you pass in an
        // old expiration date
        setcookie(Resource::COOKIE_NAME,
            $userAuth['Authentication_Challenge'], time() - 3600);
        
        return array(
            200,
            array(
                'message' => 'okay'
            )
        );
    }
}


/**
 * @uri /authentication/create
 */
class AuthenticationCreate extends Resource {


    /**
     *
     * @method GET
     */
    public function fetch() {
        throw new Tonic\MethodNotAllowedException();
    }


    /**
     *
     * @method PUT
     */
    public function createUser() {
        // make sure the user is not currently logged in.
        if ($this->isUserAuthenticated()) {
            throw new Base\ValidationException(
                array(
                    'session' => 'already logged in as a user'
                ));
        }
        

        $db = $this->getDB();
        $data = $this->getRequestData();
        if (!$data->{'username'} || !$data->{'password'} || !$data->{'source'} ||
             !$data->{'contact'} || !is_string($data->{'username'}) ||
             !is_string($data->{'password'}) || !is_string($data->{'contact'}) ||
             !is_string($data->{'source'})) {
            var_dump($data);
            throw new Base\ValidationException(
                array(
                    'json' => 'invalid request data'
                ));
        }
        
        // FIXME make this configurable per source
        $password = WebRiffs\AuthenticationLayer::hashPassword(
            $data->{'password'});
        
        $userId = WebRiffs\AuthenticationLayer::createUser($db,
            $data->{'username'}, $this->getSourceId($data->{'source'}),
            $data->{'username'}, $password, $data->{'contact'}, false);
        
        // don't expose the internal ID to the user.
        return array(
            200,
            array(
                "message" => "User created successfully."
        ));
    }
}
