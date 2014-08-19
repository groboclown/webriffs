<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;
use GroboVersion;


/**
 * Manages the business logic related to the branches.
 */
class BranchLayer {
    public static $DEFAULT_TEMPLATE_ACCESS_NAME = "standard";
    
    public static $BRANCH_SORT_COLUMNS;
    public static $DEFAULT_BRANCH_SORT_COLUMN = 'name';
    public static $BRANCH_FILTERS;

    public static $BRANCH_VERSION_SORT_COLUMNS;
    public static $DEFAULT_BRANCH_VERSION_SORT_COLUMN = 'change';
    public static $BRANCH_VERSION_FILTERS;

    public static $NAME_SEARCH_FILTER;
    public static $DESCRIPTION_SEARCH_FILTER;
    public static $BRANCH_VERSIONS_AFTER_FILTER;
    
    /**
     * Maximum number of tags allowable on a branch.
     *
     * @var int
     */
    public static $MAXIMUM_TAG_COUNT = 40;
    
    
    public static function doesBranchExist($db, $filmId, $branchName) {
        $data = VFilmBranchHead::$INSTANCE->countBy_Film_Id_x_Branch_Name($db,
                $filmId, $branchName);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the branch'
                )));
        return $data['result'] > 0;
    }
    
    
    /**
     *
     *
     * @param unknown $db
     * @param int $filmId
     * @param int $gaUserId
     * @param String $branchName
     * @param String $accessTemplate can be null; defaults to
     *      $DEFAULT_TEMPLATE_ACCESS_NAME
     */
    public static function createBranch($db, $filmId, $userId, $gaUserId,
            $branchName, $description, $accessTemplate) {
        
        // A quick check to see if the film ID is valid, and captures the
        // project ID
        $data = Film::$INSTANCE->readBy_Film_Id($db, $filmId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the branch'
                )));
        if (sizeof($data['result']) <= 0) {
            throw new Base\ValidationException(array(
                'filmid' => 'no film with ID '.$filmId.' exists'
            ));
        }
        
        $projectId = $data['result'][0]['Gv_Project_Id'];
        
        return BranchLayer::createBranchById($db, $projectId, $filmId, $userId,
                $gaUserId, $branchName, $description, $accessTemplate);
    }


    /**
     * For internal use; creates a branch when the film ID and project ID are
     * already known.
     * It also creates a new change to allow the user to
     * start editing right away!
     *
     * @param PBO $db
     * @param int $projectId
     * @param int $filmId
     * @param string $branchName
     * @return multitype:number
     */
    public static function createBranchById($db, $projectId, $filmId, $userId,
            $gaUserId, $branchName, $description, $accessTemplate) {
        
        if ($accessTemplate == null) {
            $accessTemplate = BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME;
        }
        
        if (BranchLayer::doesBranchExist($db, $filmId, $branchName)) {
            throw new Base\ValidationException(
                array(
                    'Branch_Name' => 'A branch with that name already exists'
                ));
        }
        
        // FIXME ensure the branch name is valid
        // FIXME validate branch name length is valid
        
        // We first need a Gv Branch
        $data = GroboVersion\GvBranch::$INSTANCE->create($db, $projectId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the branch'
                )));
        $branchId = intval($data['result']);
        
        
        // Then the branch itself
        $branchItemId = GroboVersion\DataAccess::createItem($db);
        
        $data = FilmBranch::$INSTANCE->create($db, $branchId, $branchItemId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film branch'
                )));
        
        
        // Next set the permissions based on the template
        $data = FilmBranchAccess::$INSTANCE->runCreateFromTemplate($db,
                $branchId, $accessTemplate);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the access permissions'
                )));
        
        
        // Add the user as the owner of the branch.
        foreach (Access::$BRANCH_ACCESS as $ba) {
            $data = FilmBranchAccess::$INSTANCE->create($db, $branchId,
                $userId, $ba, Access::$PRIVILEGE_OWNER);
        }


        // Add a name to the branch and submit the change.
        $tags = array();
        BranchLayer::updateBranchHeader($db, $branchId, $userId, $gaUserId,
            $branchName, $description, $tags, false);
        
        
        // Add change for the user to start using.
        $changeId = GroboVersion\DataAccess::createChange($db,
                $branchId, $gaUserId);
        
        
        return array(
            $branchId,
            $changeId
        );
    }


    /**
     * Returns the head revision of all the branches in the film that are
     * visible by the user.
     */
    public static function pageBranches($db, $userId, $filmId,
            Base\PageRequest $paging = null) {
        // userId can be null
        
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    BranchLayer::$BRANCH_FILTERS,
                    BranchLayer::$DEFAULT_BRANCH_SORT_COLUMN,
                    BranchLayer::$BRANCH_SORT_COLUMNS);
        }
        
        // We need a bit of dual logic here for the situation where the
        // user isn't logged in.
        
        $wheres = array();
        if ($paging->filters[BranchLayer::$NAME_SEARCH_FILTER->name] !== null) {
            $wheres[] = new VFilmBranchHead_BranchNameLike(
                    '%' . $paging->filters[BranchLayer::$NAME_SEARCH_FILTER->name] .
                    '%');
        }
        if ($paging->filters[BranchLayer::$DESCRIPTION_SEARCH_FILTER->name] !== null) {
            $wheres[] = new VFilmBranchHead_BranchDescriptionLike(
                    '%' . $paging->filters[BranchLayer::$DESCRIPTION_SEARCH_FILTER->name] .
                    '%');
        }
        
        if ($userId === null) {
            $wheres[] = new VFilmBranchGuestAccess_IsAllowed(
                    Access::$PRIVILEGE_GUEST, Access::$BRANCH_READ
                );
            
            $rowData = VFilmBranchGuestAccess::$INSTANCE->readBy_Film_Id(
                    $db, $filmId, $wheres,
                    $paging->order, $paging->startRow, $paging->endRow);
            $countData = VFilmBranchGuestAccess::$INSTANCE->countBy_Film_Id(
                    $db, $filmId, $wheres);
        } else {
            $wheres[] = new VFilmBranchAccess_IsAllowed();
            
            $rowData = VFilmBranchAccess::$INSTANCE->readBy_Film_Id_x_User_Id_x_Access(
                    $db, $filmId, $userId, Access::$BRANCH_READ, $wheres,
                    $paging->order, $paging->startRow, $paging->endRow);
            $countData = VFilmBranchAccess::$INSTANCE->countBy_Film_Id_x_User_Id_x_Access(
                    $db, $filmId, $userId, Access::$BRANCH_READ);
        }
        BranchLayer::checkError($rowData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branches'
                )));
        $rows = $rowData['result'];
        
        BranchLayer::checkError($countData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the branches'
                )));
        $count = intval($countData['result'][0]);

        // Add the tags
        // Use a for rather than foreach, so that we directly affect the
        // row data, rather than a copy of each row's data.
        for ($i = 0; $i < sizeof($rows); ++$i) {
            $rows[$i]['Film_Id'] = intval($rows[$i]['Film_Id']);
            $rows[$i]['Gv_Branch_Id'] = intval($rows[$i]['Gv_Branch_Id']);
            $rows[$i]['Gv_Change_Id'] = intval($rows[$i]['Gv_Change_Id']);
            $data = VBranchTagHead::$INSTANCE->readBy_Gv_Branch_Id($db,
                $rows[$i]['Gv_Branch_Id']);
            BranchLayer::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem reading the branch tags'
                    )));
            $tags = array();
            foreach ($data['result'] as $tagRow) {
                $tags[] = $tagRow['Tag_Name'];
            }
            $rows[$i]['tags'] = $tags;
        }
        
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }
    
    
    /**
     * Performs the correct access check for the user on the branch.  Returns
     * false if the user cannot access the branch, or true if it can.
     *
     * It's fine to split up general queries on the branch into two separate
     * queries.  There's a very slim chance
     * that the user will have permissions revoked in the middle of these
     * two queries, but that results in a very minor data leak that the
     * user should have had permissions to see if they made this query just
     * a half second earlier.
     *
     * @param PBO $db
     * @param int $userId
     * @param int $branchId
     * @param String $access
     * @return bool true if access is allowed, false if not.
     */
    public static function canAccessBranch($db, $userId, $branchId, $access) {
        
        // We need to split the query into two types.
        
        if ($userId === null) {
            // guest user
            $wheres = array(new VFilmBranchGuestAccess_IsAllowed(
                    $access, Access::$PRIVILEGE_GUEST));
            //error_log("Checking if anonymous user can access branch ".$branchId." for ".$access." with level ".Access::$PRIVILEGE_GUEST);
            $data = VFilmBranchGuestAccess::$INSTANCE->countBy_Gv_Branch_Id(
                    $db, $branchId, $wheres);
        } else {
            // logged-in user
            //error_log("Checking if user ".$userId." can access branch ".$branchId." for ".$access);
            $wheres = array(new VFilmBranchAccess_IsAllowed());
            $data = VFilmBranchAccess::$INSTANCE->countBy_Gv_Branch_Id_x_User_Id_x_Access(
                    $db, $branchId, $userId, $access, $wheres);
        }
        
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the branches'
                )));
        
        return $data['result'] > 0;
    }


    public static function getHeadBranchVersion($db, $userId, $branchId) {
        if (! BranchLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = VFilmBranchHead::$INSTANCE->readBy_Gv_Branch_Id(
                 $db, $branchId);
        BranchLayer::checkError($rowData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branches'
                )));
        return $data['result'][0];
    }
    
    
    /**
     * Page in the different versions of the branch.
     *
     * @param unknown $db
     * @param unknown $userId
     * @param unknown $branchId
     * @param Base\PageRequest $paging
     * @return multitype:
     */
    public static function pageBranchVersions($db, $userId, $branchId,
            Base\PageRequest $paging = null) {
        // userId can be null
        
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    BranchLayer::$BRANCH_VERSION_FILTERS,
                    BranchLayer::$DEFAULT_BRANCH_VERSION_SORT_COLUMN,
                    BranchLayer::$BRANCH_VERSION_SORT_COLUMNS);
        }
        
        if (! BranchLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            return Base\PageResponse::createPageResponse($paging, 0, array());
        }
        
        
        $wheres = array();
        if ($paging->filters[
                BranchLayer::$BRANCH_VERSIONS_AFTER_FILTER->name] !== null) {
            $wheres[] = new VFilmBranchVersion_VersionsAfter(
                $paging->filters[BranchLayer::$BRANCH_VERSIONS_AFTER_FILTER->name]
            );
        }
        
        
        $rowData = VFilmBranchVersion::$INSTANCE->readBy_Gv_Branch_Id(
                $db, $branchId, $wheres,
                $paging->order, $paging->startRow, $paging->endRow);
        $countData = VFilmBranchVersion::$INSTANCE->countBy_Gv_Branch_Id(
                $db, $branchId);
        BranchLayer::checkError($rowData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branch versions'
                )));
        $rows = $rowData['result'];
        
        BranchLayer::checkError($countData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the branches'
                )));
        $count = intval($countData['result'][0]);

        // Don't add the tags.  Note that this includes both header changes
        // and quip changes.
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }
    
    
    /**
     * Return the details for the film's branch.  This is all the header
     * information about it.  It will also include details about the film.
     * If the user is not authenticated to see the details, false is returned.
     *
     * @param PBO $db
     * @param int $userId
     * @param int $branchId
     * @param int $changeId
     * @return mixed false if no data, array of results if the branch is
     *      readable by the user and it exists.
     */
    public static function getBranchDetails($db, $userId, $branchId, $changeId) {
        if (! BranchLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            return false;
        }
        
        if ($changeId <= 0) {
            // Get the head revision
            $data = VFilmBranchHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        } else {
            $data = VFilmBranchVersion::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                 $db, $branchId, $changeId);
        }
        
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branch'
                )));
        if (sizeof($data['result']) <= 0) {
            return false;
        }
        if (sizeof($data['result']) > 1) {
            throw new Base\ValidationException(
                array(
                    'internal error' => 'too many branches with that ID and change'
                ));
        }
        $result = $data['result'][0];
        
        if ($changeId <= 0) {
            $data = VBranchTagHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        } else {
            $data = VBranchTagVersion::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                    $db, $branchId, $changeId);
        }
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the branch tags'
                )));
        $tags = array();
        foreach ($data['result'] as $tagRow) {
            $tags[] = $tagRow['Tag_Name'];
        }
        $result['tags'] = $tags;
        return $result;
    }
    
    
    /**
     * Update all the header-level details for the branch.  This will create
     * a new change and submit it.
     *
     * @param PBO $db
     * @param int $branchId
     * @param int $userId
     * @param int $gaUserId
     * @param String $newName
     * @param String $newDescription
     * @param array(string) $tagList
     * @throws Tonic\UnauthorizedException
     * @return the change ID that was committed
     */
    public static function updateBranchHeader($db, $branchId, $userId,
                $gaUserId, $newName, $newDescription, &$tagList,
                $checkAccess = true) {
        // userId CANNOT be null
        
        if ($userId === null || ($checkAccess &&
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
                        Access::$BRANCH_WRITE))) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = FilmBranch::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branch item'
                )));
        if ($data['rowcount'] != 1) {
            throw new Base\ValidationException(array(
                'branchId' => 'Unknown branch ID'
            ));
        }
        $branchItemId = $data['result'][0]['Gv_Item_Id'];
        
        // Branch header updates happen all at once in a new change.
        $branchChangeId = GroboVersion\DataAccess::createChange($db,
                $branchId, $gaUserId);
        $idSet = GroboVersion\DataAccess::addItemToChange($db, $branchItemId,
                $branchChangeId);
        $data = FilmBranchVersion::$INSTANCE->create($db, $idSet[0],
                $newName, $newDescription);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the change version'
                )));
        
        BranchLayer::updateAllTagsOnBranchByChange($db, $userId, $gaUserId,
                $branchId, $branchChangeId, $tagList, false);
        
        return GroboVersion\DataAccess::commitChange($db, $branchChangeId, $gaUserId);
    }
    
    
    
    /**
     * Creates and submits a change for the branch, which only updates the
     * tag list.
     *
     * @param unknown $db
     * @param unknown $userId
     * @param unknown $gaUserId
     * @param unknown $branchId
     * @param unknown $tagList
     * @param string $checkAccess
     * @return the change ID that was committed
     */
    public static function updateAlltagsOnBranch($db, $userId, $gaUserId,
                $branchId, &$tagList, $checkAccess = true) {
        // userId CANNOT be null
        
        if ($userId === null || ($checkAccess &&
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
                        Access::$BRANCH_WRITE))) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = FilmBranch::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branch item'
                )));
        if ($data['rowcount'] != 1) {
            throw new Base\ValidationException(array(
                            'branchId' => 'Unknown branch ID'
            ));
        }
        $branchItemId = $data['result'][0]['Gv_Item_Id'];

        // Branch header updates happen all at once in a new change.
        $branchChangeId = GroboVersion\DataAccess::createChange($db,
                $branchId, $gaUserId);
        BranchLayer::updateAllTagsOnBranchByChange($db, $userId, $gaUserId,
                $branchId, $branchChangeId, $tagList, false);

        return GroboVersion\DataAccess::commitChange($db, $branchChangeId, $gaUserId);
    }
    
    
    
    /**
     * Force the setting of all the tags for a branch.  These are added to a
     * changelist that is specific for the branch header, and not to a user's
     * pending quips.
     *
     * @param unknown $db
     * @param unknown $userId
     * @param unknown $gaUserId
     * @param unknown $changeId
     * @param unknown $tagList
     * @param unknown $checkAccess
     * @throws Tonic\UnauthorizedException
     * @throws Base\ValidationException
     */
    public static function updateAllTagsOnBranchByChange($db, $userId, $gaUserId,
            $branchId, $changeId, &$tagList, $checkAccess) {
        // userId CANNOT be null
        
        if ($userId === null || ($checkAccess &&
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_TAG))) {
            throw new Tonic\UnauthorizedException();
        }

        $tagMap = array();
        // Mark each tag as added.  This will be updated when we compare this
        // list against what's in the database.
        foreach ($tagList as $tag) {
            $tag = BranchLayer::normalizeTagName($tag);
            $tagMap[$tag] = true;
        }
        
        // Find the deltas on the tag list
        $data = VBranchTagHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        BranchLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the branch tags'
                )));
        
        foreach ($data['result'] as $tagRow) {
            $tag = $tagRow['Tag_Name'];
            if (array_key_exists($tag, $tagMap)) {
                // don't do anything with it
                $tagMap[$tag] = false;
            } else {
                // delete it
                BranchLayer::updateTagOnBranch($db, $userId, $branchId,
                    $tag, $tagRow['Tag_Gv_Item_Id'], $changeId, true, false);
            }
        }
        
        // Add each new tag.
        foreach ($tagMap as $tag => $added) {
            if ($added) {
                // We don't know the tag id, so set it to null
                BranchLayer::updateTagOnBranch($db, $userId, $branchId, $tag,
                    null, $changeId, false, false);
            }
        }
    }
    
    
    public static function updateTagOnBranch($db, $userId, $branchId,
            $tagName, $tagId, $changeId, $removed, $checkAccess) {
        // userId CANNOT be null
        
        if ($checkAccess && (! $userId ||
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
            Access::$BRANCH_TAG))) {
                    throw new Tonic\UnauthorizedException();
        }
        
        // Normalize the tag name
        $tagName = BranchLayer::normalizeTagName($tagName);
        
        if ($tagId === null) {
            // Find the tag's item ID.  If it doesn't exist, create it.
            $data = BranchTag::$INSTANCE->readBy_Name($db, $tagName);
            BranchLayer::checkError($data,
                new Base\ValidationException(
                    array(
                        'unknown' => 'there was an unknown problem finding the branch tags'
                    )));
            $tagId = null;
            if ($data['rowcount'] > 1) {
                throw new Base\ValidationException(
                        array($tag => "multiple entries"));
            }
            if ($data['rowcount'] == 1) {
                $tagId = $data['result'][0]['Gv_Item_Id'];
            } else {
                // Create the tag id
                $tagId = GroboVersion\DataAccess::createItem($db);
                $data = BranchTag::$INSTANCE->create($db, $tagId, $tagName);
                BranchLayer::checkError($data,
                    new Base\ValidationException(
                        array(
                            "Tags/$tagName" => 'there was an unknown problem adding the branch tag'
                        )));
            }
        }
        
        // Add the item to the given change ID.
        GroboVersion\DataAccess::addItemToChange($db, $tagId, $changeId, $removed);
        
        // DO NOT submit the change.
    }
    
    
    

    // ----------------------------------------------------------------------
    
    
    // Clean tag name so it can be added or match existing tags:
    //  - trim whitespace at the start and end of the string.
    //  - replace connected whitespace in the middle with a single underscore
    //  - lowercase it.
    public static function normalizeTagName($tagName) {
        return strtolower(preg_replace('/\s+/', '_', trim($tagName)));
    }
    
    
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}
BranchLayer::$NAME_SEARCH_FILTER =
    new Base\SearchFilterString("name", null);
BranchLayer::$DESCRIPTION_SEARCH_FILTER =
    new Base\SearchFilterString("description", null);
BranchLayer::$BRANCH_VERSIONS_AFTER_FILTER =
    new Base\SearchFilterInt("versions_after", 1, 1, 10000000000);


BranchLayer::$BRANCH_SORT_COLUMNS = array(
    "name" => "Branch_Name",
    "description" => "Description",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

BranchLayer::$BRANCH_FILTERS = array(
    BranchLayer::$NAME_SEARCH_FILTER,
    BranchLayer::$DESCRIPTION_SEARCH_FILTER
);


BranchLayer::$BRANCH_VERSION_SORT_COLUMNS = array(
    "change" => "Gv_Change_Id",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

BranchLayer::$BRANCH_VERSION_FILTERS = array(
    BranchLayer::$BRANCH_VERSIONS_AFTER_FILTER
);

