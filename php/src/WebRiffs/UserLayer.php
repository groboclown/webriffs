<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;


/**
 * General Admin tools and functions.
 * The user will need to be a site admin
 * to perform these actions.
 */
class UserLayer {
    public static $USER_SORT_COLUMNS;
    public static $DEFAULT_USER_SORT_COLUMN = "name";
    public static $USER_FILTERS;
    
    public static $NAME_SEARCH_FILTER;
    
    /**
     * Pulls in all the users and their data.
     *
     * Access (is admin?) needs to be checked before this enters.
     */
    public static function pageUsers($db, Base\PageRequest $paging = null) {
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    UserLayer::$USER_FILTERS, UserLayer::$DEFAULT_USER_SORT_COLUMN,
                    UserLayer::$USER_SORT_COLUMNS);
        }
        // TODO add wheres
        
        $data = User::$INSTANCE->readAll($db, /*wheres,*/ $paging->order,
            $paging->startRow, $paging->endRow);
        UserLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the users'
                )));
        $rows = $data['result'];
        foreach ($rows as &$row) {
            $row['User_Id'] = intval($row['User_Id']);
            $row['Ga_User_Id'] = intval($row['Ga_User_Id']);
            $row['Primary_Source_Id'] = intval($row['Primary_Source_Id']);
            $row['Is_Site_Admin'] = intval($row['Is_Site_Admin']) == 0 ? FALSE : TRUE;
            $row['Is_Perma_Banned'] = intval($row['Is_Perma_Banned']) == 0 ? FALSE : TRUE;
        }

        $data = User::$INSTANCE->countAll($db);
        UserLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the users'
                )));
        $count = $data['result'];
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }

    
    /**
     * Load in the user data, with the amount of data based upon the access
     * level given.
     *
     * @param unknown $db
     * @param str $username
     * @param int $access 0 => very limited access; 1 => public info;
     *    2 => current user's view of herself; 3 => all data (admin view)
     */
    public static function loadUser($db, $username, $access) {
        if ($username == null) {
            return false;
        }
        $username = strval($username);
        $data = User::$INSTANCE->readBy_Username($db, $username);
        UserLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user'
                )));
        if ($data['rowcount'] != 1) {
            if ($data['rowcount'] > 1) {
                error_log("too many rows with user name [".$username."]");
                throw new Exception("db validation error");
            }
            return false;
        }
        
        $row = $data['result'][0];
        $ret = array(
           'username' => $row['Username'],
           'user_id' => null,
           'is_admin' => null,
           'ban_start' => null,
           'ban_end' => null,
           'is_perma_ban' => null,
           'created' => $row['Created_On'],
           'authentication' => array(),
           'attributes' => array()
        );
        
        if ($access <= 0 || $access > 3) {
            return $ret;
        }
        
        // Next level of information: public info.
        $ret['is_admin'] = intval($row['Is_Site_Admin']) == 0 ? FALSE : TRUE;
        $ret['ban_start'] = $row['Ban_Start'];
        $ret['ban_end'] = $row['Ban_End'];
        $ret['is_perma_ban'] = intval($row['Is_Perma_Banned'] == 0) ? FALSE : TRUE;

        $userId = intval($row['User_Id']);
        $gaUserId = intval($row['Ga_User_Id']);
        
        
        // TODO: public user attributes
        
        $data = UserAttribute::$INSTANCE->readBy_User_Id($db, $userId);
        UserLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the user attributes'
                )));
        $attributeRows = $data['result'];
        
        if ($access == 1) {
            return $ret;
        }
        
        // Next level of information: current user
        
        $ret['user_id'] = $userId;
        $retattr = &$ret['attributes'];
        
        foreach ($attributeRows as &$arow) {
            // FIXME public check
            $retattr[$arow['Attribute_Name']] = $arow['Attribute_Value'];
        }
        
        // Next level of information: user admin
        
        return $ret;
    }


    // ----------------------------------------------------------------------
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}



UserLayer::$NAME_SEARCH_FILTER =
    new Base\SearchFilterString("name", null);

UserLayer::$USER_SORT_COLUMNS = array(
    "name" => "Username",
    "contact" => "Contact",
    "is_admin" => "Is_Site_Admin"
);

UserLayer::$USER_FILTERS = array(
    UserLayer::$NAME_SEARCH_FILTER,
);