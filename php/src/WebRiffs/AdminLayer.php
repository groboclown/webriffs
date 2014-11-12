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
    public static $LINK_FILTERS;
    
    public static $NAME_SEARCH_FILTER;
    public static $URL_SEARCH_FILTER;
    

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
        // TODO standardize invocation
        
        
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
        foreach ($rows as &$row) {
            $row['Is_Media'] = intval($row['Is_Media']) == 0 ? FALSE : TRUE;
        }

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
    public static function getLinkNamed($db, $name) {
        if (! is_string($name) || strlen($name) > 200) {
            throw new Base\ValidationException(array(
                "link name" => "link name cannot be more than 200 characters long"
                ));
        }
        
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
        $rows[0]['Is_Media'] = intval($rows[0]['Is_Media']) == 0 ? FALSE : TRUE;
        return $rows[0];
    }


    /**
     * Creates a new link.
     *
     * @return int the link_id for the link
     */
    public static function createLink($db, $name, $description,
            $urlPrefix, $validationRegex, $mediaProvider) {
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
        if ($mediaProvider != null && (! is_string($mediaProvider) || strlen($mediaProvider) > 30)) {
            $errors["media_provider"] = "media_provider cannot be more than 30 characters long";
        }
        if (sizeof($errors) > 0) {
            throw new Base\ValidationException($errors);
        }
        $isMedia = ($mediaProvider == null) ? 0 : 1;
        $data = LinkType::$INSTANCE->create($db, $name, $description,
            $isMedia, $mediaProvider, $urlPrefix, $validationRegex);
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



AdminLayer::$LINK_SORT_COLUMNS = array(
    "name" => "Name",
    "description" => "Description",
    "url" => "Url_Prefix",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

AdminLayer::$NAME_SEARCH_FILTER =
    new Base\SearchFilterString("name", null);
AdminLayer::$URL_SEARCH_FILTER =
    new Base\SearchFilterString("url", null);


AdminLayer::$LINK_FILTERS = array(
    AdminLayer::$NAME_SEARCH_FILTER,
    AdminLayer::$URL_SEARCH_FILTER
);
