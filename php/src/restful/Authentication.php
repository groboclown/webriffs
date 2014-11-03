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
        
        $db = $this->getDB();
        $access = WebRiffs\AuthenticationLayer::getUserAccess(
                $db, $user['User_Id']);
        $canEditFilms = false;
        if (array_key_exists(WebRiffs\Access::$FILM_MODIFICATION, $access) &&
                $access[WebRiffs\Access::$FILM_MODIFICATION] >=
                    WebRiffs\Access::$PRIVILEGE_TRUSTED) {
            $canEditFilms = true;
        }

        $canCreateFilms = false;
        if (array_key_exists(WebRiffs\Access::$FILM_CREATE, $access) &&
                $access[WebRiffs\Access::$FILM_CREATE] >=
                    WebRiffs\Access::$PRIVILEGE_TRUSTED) {
            $canCreateFilms = true;
        }

        $canCreateBranch = false;
        // NOTE: any logged in user can create a branch.
        if (array_key_exists(WebRiffs\Access::$FILM_BRANCH, $access) &&
                $access[WebRiffs\Access::$FILM_BRANCH] >=
                    WebRiffs\Access::$PRIVILEGE_USER) {
            $canCreateBranch = true;
        }
        
        return array(
            200,
            array(
                'username' => $user['Username'],
                'contact' => $user['Contact'],
                'is_admin' => $user['Is_Admin'],
                'created_on' => $user['Created_On'],
                'last_updated_on' => $user['Last_Updated_On'],
                //'access' => $access,
                'can_edit_films' => $canEditFilms,
                'can_create_films' => $canCreateFilms,
                'can_create_branch' => $canCreateBranch,
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
        $this->assertThat(is_string($action) && !! $action, "bad action");
        
        $user = $this->container['user'];
        $sessionId = $user['Ga_Session_Id'];
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
     * @method POST
     */
    public function login() {
        $username = $this->loadRequestString("username");
        $password = $this->loadRequestString("password");
        $source = $this->loadRequestString("source");
        $this->validate();
        
        // TODO make the session minutes settable by the user,
        // within reason
        $timeout = Resource::DEFAULT_SESSION_TIMEOUT;
        
        $db = $this->getDB();
        $userData = WebRiffs\AuthenticationLayer::login($db,
            $username, $this->getSourceId($source),
            $password,
            // TODO make this configurable per source
            function ($u, $p, $h) {
                return WebRiffs\AuthenticationLayer::validatePassword(
                        $u, $p, $h);
            },

            $this->request->userAgent, $this->request->remoteAddr, null,
            $timeout);


        // FIXME add IP and user ban checking
        
        
        // Note that by setting the domain to an empty string, PHP will restrict
        // the cookie to a single domain.  May need to try getenv('HTTP_HOST')
        setcookie(Resource::COOKIE_NAME, $userData['Authentication_Challenge'],
            time() + ($timeout*60), $this->container['path'], '', false, true);
        
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
     * @csrf logout
     * @method POST
     */
    public function logout() {
        $userAuth = $this->container['user'];
        
        $db = $this->getDB();
        //error_log('User auth value: '.print_r($userAuth, true));
        WebRiffs\AuthenticationLayer::logout($db, $userAuth['User_Id'],
            $userAuth['Ga_Session_Id']);
        
        // Some old browsers will only delete the cookie if you pass in an
        // old expiration date
        setcookie(Resource::COOKIE_NAME,
            $userAuth['Authentication_Challenge'], time() - 3600,
            $this->container['path'], '', false, true);
        
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
        
        $username = $this->loadRequestString("username");
        $password = $this->loadRequestString("password");
        $source = $this->loadRequestString("source");
        $contact = $this->loadRequestString("contact");
        $this->validate();
        
        // FIXME make this configurable per source
        $password = WebRiffs\AuthenticationLayer::hashPassword($password);
        
        // FIXME the admins should have access to escalating individuals
        // to higher levels, and so the default access should be
        // PRIVILEGE_USER.  However, for the initial versions, we set this
        // to trusted.
        $userId = WebRiffs\AuthenticationLayer::createUser($db,
            $username, $this->getSourceId($source),
            $username, $password, $contact,
            WebRiffs\Access::$PRIVILEGE_TRUSTED);
        
        // don't expose the internal ID to the user.
        return array(
            200,
            array(
                "message" => "User created successfully."
        ));
    }
}
