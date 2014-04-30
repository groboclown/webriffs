<?php

namespace WebRiffs;

use Tonic;


/**
 * @uri /auth/login
 */
class AuthorizationLogin extends Resource {
    /**
     * @method POST
     * @accepts application/json
     * @provides application/json
     * @json
     * @return Tonic\Response
     */
    public function login() {
        $db = getDB();
        $auth = isAuthorized($db);
        if ($auth) {
            authLogoutById($db, $auth['user_login_id']);
        }

        // FIXME for now, just login the user.  If the user doesn't exist,
        // create it.
        $username = $this->request->data['username'];

        $db = getDB();

        $stmt = $db->('SELECT User_Id, Username FROM USER WHERE Username = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($user));

        $row = $stmt->fetch();
        if (!$row) {
            $salt = openssl_random_pseudo_bytes(128);

            // create the user
            $stmt = $db->('INSERT INTO USER (Username, Email, Authentication_Source, Authentication_Code, Salt, Last_Access, Created_On) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute(array($username, $username . '@nowhere', 'local', 'encrypted password', $salt));

            $userId = $db->lastInsertId();
        } else {
            $userId = $row['User_Id'];
            $username = $row['Username'];

            $stmt = $db->('UPDATE USER SET Last_Access = NOW(), Last_Updated_On = NOW() WHERE User_Id = ?');
            $stmt->execute(array($userId));
        }


        // Create the authorization value
        $authchallenge = base64_encode(openssl_random_pseudo_bytes(64));
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
        //$remoteAddr = $_SERVER['REMOTE_ADDR'];
        $remoteAddr = NULL;

        $stmt = $db->('INSERT INTO USER_LOGIN (User_Id, User_Agent, Remote_Address, Forwarded_For, Authorization_Challenge, Expires_On, Created_On, Last_Update_On) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NULL)');
        $stmt->execute(array($userId, $userAgent, $remoteAddr, $forwardedFor, $authchallenge));
    }
}




/**
 * @uri /auth/logout
 */
class AuthorizationLogout extends Resource {
    /**
     * @method POST
     * @accepts application/json
     * @provides application/json
     * @json
     * @return Tonic\Response
     */
    public function logout() {
        $db = getDB();
        $auth = isAuthorized($db);
        if (!$auth) {
            throw Tonic\UnauthorizedException;
        }
        authLogoutById(getDB(), $auth['user_login_id']);
    }
}




/**
 * Return false if not authorized, otherwise an array with user_id, username,
 * and login_time.
 */
function isAuthorized($db) {
    $authchallenge = $_COOKIE['authchallenge'];

    if (!$authchallenge) {
        return false;
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $remoteAddr = $_SERVER['REMOTE_ADDR'];

    // can ignore remoteAddr - it's fine for people to move from hotspot to hotspot
    // can ignore forwarded if it wasn't set initially
    // TODO check for timeout with the Expires_On column

    $stmt = $db->('SELECT User_Id, Username, Created_On, User_Login_Id FROM USER_LOGIN WHERE Authorization_Challenge = ? AND User_Agent = ? AND (Remote_Address IS NULL OR Remote_Address = ?) AND (Forwarded_For IS NULL OR Forwarded_For = ?)');
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $stmt->execute(array($authchallenge, $userAgent, $remoteAddr, $forwardedFor));

    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    // If there is a second row, then that could be an issue with the login
    // code.
    $ret = array(
        'user_id' => $stmt['User_Id'],
        'username' => $stmt['Username'],
        'login_time' => $stmt['Created_On'],
        'user_login_id' => $stmt['User_Login_Id'],
        'attributes' => array();
    );

    $stmt = $db->('SELECT Attribute_Name, Attribute_Value FROM USER_ATTRIBUTE WHERE User_Id = ?');
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $stmt->execute(array($ret['user_login_id']);
    while ($row = $stmt->fetch()) {
        $ret['attributes'][$row['Attribute_Name']] = $row['Attribute_Value'];
    }

    return $ret;
}


function authLogoutById($db, $loginId) {
    $stmt = $db->('DELETE FROM USER_LOGIN WHERE User_Login_Id = ?');
    $stmt->execute(array($loginId));
}
