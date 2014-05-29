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
class AdminLayer {
    public static $LINK_SORT_COLUMNS;
    public static $DEFAULT_LINK_SORT_COLUMN = "name";


    /**
     * Returns all the links.
     * The Paging object should be trusted, as it performs all the input
     * validation.
     *
     * @param PBO $db
     * @param Base\PageRequest $paging
     * @return array a page response json array.
     */
    public static function pageLinks($db, Base\PageRequest $pageReq) {
        $order = $pageReq->order;
        $startRow = $pageReq->startRow;
        $endRow = $pageReq->endRow;
        
        $data = LinkType::$INSTANCE->readAll($db, $order, $startRow, $endRow);
        AdminLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the links'
                )));
        $rows = $data['result'];
        
        $data = LinkType::$INSTANCE->countAll($db);
        AdminLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the links'
                )));
        $count = $data['result'];
        
        return Base\PageResponse::createPageResponse($pageReq, $count, $rows);
    }
    
    
    /**
     *
     * @param PBO $db
     * @param string $name
     * @return null (no link with that name) or an associative array with the
     *      values.
     */
    public static function getLinkNamed($db, string $name) {
        $data = LinkType::$INSTANCE->readBy_Name($db, $name);
        AdminLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the link'
                )));
        $rows = $data['result'];
        if (sizeof($rows) <= 0) {
            return null;
        }
        if (sizeof($rows) > 1) {
            throw new Base\ValidationException(
                 array("name" => "internal error, multiple links with that name")
            );
        }
        return $rows[0];
    }


    /**
     * Creates a new link.
     *
     * @return int the link_id for the link
     */
    public static function createLink($db, string $name, string $description,
            string $urlPrefix, string $validationRegex) {
        //public function create($db, $Name, $Description, $Url_Prefix, $Validation_Regex) {
        $errors = array();
        if (! is_string($name) || strlen($name) > 200) {
            $errors["name"] = "name cannot be more than 200 characters long";
        }
        if (! is_string($description) || strlen($description) > 2048) {
            $errors["description"] = "description cannot be more than 2048 characters long";
        }
        if (! is_string($urlPrefix) || strlen($urlPrefix) > 200) {
            $errors["url_prefix"] = "url_prefix cannot be more than 200 characters long";
        }
        if (! is_string($validationRegex) || strlen($validationRegex) > 500) {
            $errors["validation_regex"] = "validation_regex cannot be more than 500 characters long";
        }
        if (sizeof($errors) > 0) {
            throw new Base\ValidationException($errors);
        }
        $data = LinkType::create($db, $name, $description, $urlPrefix,
            $validationRegex);
        AdminLayer::checkError($data, new Base\ValidationException(array(
            'unknown' => 'problem creating the link.  Is the name already used?'
        )));
        return intval($data['result']);
    }
    

    // ----------------------------------------------------------------------
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}
