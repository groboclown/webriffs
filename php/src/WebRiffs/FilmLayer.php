<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;
use GroboVersion;


/**
 * Manages the business logic related to the films.
 */
class FilmLayer {
    public static $DEFAULT_TEMPLATE_ACCESS_NAME = "standard";
    public static $INITIAL_BRANCH_NAME = "Initial";
    public static $INITIAL_BRANCH_DESCRIPTION = "First branch for the film";
    
    public static $FILM_SORT_COLUMNS;
    public static $DEFAULT_FILM_SORT_COLUMN = 'name';
    public static $FILM_FILTERS;
    
    public static $BRANCH_SORT_COLUMNS;
    public static $DEFAULT_BRANCH_SORT_COLUMN = 'name';
    public static $BRANCH_FILTERS;

    public static $BRANCH_VERSION_SORT_COLUMNS;
    public static $DEFAULT_BRANCH_VERSION_SORT_COLUMN = 'change';
    public static $BRANCH_VERSION_FILTERS;
    
    public static $QUIP_SORT_COLUMNS;
    public static $DEFAULT_QUIP_SORT_COLUMN = 'timestamp';
    public static $QUIP_FILTERS;

    public static $MIN_YEAR_SEARCH_FILTER;
    public static $MAX_YEAR_SEARCH_FILTER;
    public static $NAME_SEARCH_FILTER;
    public static $DESCRIPTION_SEARCH_FILTER;
    
    /**
     * Maximum number of tags allowable on a quip or branch.
     *
     * @var int
     */
    public static $MAXIMUM_TAG_COUNT = 40;
    
    
    public static function doesFilmExist($db, $name, $year) {
        $data = Film::$INSTANCE->readBy_Name_x_Release_Year($db, $name, $year);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the films'
            )));
        return (sizeof($data['result']) > 0);
    }
    
    

    /**
     * Creates the film, and adds a new, empty film version.
     *
     * @param
     *            array userInfo must be the user session, so we don't need to
     *            check its value.
     * @return array contains the (projectId, filmId, branchId, changeId)
     */
    public static function createFilm($db, $userInfo, $name, $year, $accessTemplate) {
        
        // input validation
        if (!is_string($name) || strlen($name) < 1) {
            throw new Base\ValidationException(
                array(
                    "name" => "invalid name format"
                ));
        }
        // FIXME Should also remove extra spaces in the middle (shrink >=2
        // whitespace into 1 space).
        $name = trim($name);
        if (strlen($name) < 1) {
            throw new Base\ValidationException(
                array(
                    "name" => "invalid name format"
                ));
        }
        if (!is_integer($year)) {
            throw new Base\ValidationException(
                array(
                    "year" => "invalid year format"
                ));
        }
        $year = intval($year);
        if ($year < 0 || $year > 9999) {
            throw new Base\ValidationException(
                array(
                    "year" => "invalid year"
                ));
        }
        
        if (!$userInfo || !is_array($userInfo)) {
            throw new Base\ValidationException(
                array(
                    "user" => "user does not not have sufficient permissions"
                ));
        }
        
        $userId = $userInfo['User_Id'];
        $gaUserId = $userInfo['Ga_User_Id'];
        if ($userId === null || $gaUserId === null) {
            error_log("found bad user info array ".print_r($userInfo, true));
            throw new Base\ValidationException(
                array(
                    'user' => 'invalid setup of the user data'
                ));
        }
        // FIXME need better permission checks.  Though, this should be done
        // at the entry level.
        


        // We'll first check if the name/release year are already taken.
        // This isn't a guarantee no one will take it between here and the
        // create statement, though.  The film table has a unique
        // constraint on the year+name, so that will prevent the duplicate
        // creation, but the project will still exist.  We can live with that.
        
        if (FilmLayer::doesFilmExist($db, $name, $year)) {
            throw new Base\ValidationException(
                array(
                    'name,year' => 'the name and release year already exist'
                ));
        }
        
        $data = GroboVersion\GvProject::$INSTANCE->create($db);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem setting up the versioning'
                )));
        $projectId = intval($data['result']);
        
        $data = Film::$INSTANCE->create($db, $projectId, $name, $year);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film record'
                )));
        $filmId = intval($data['result']);
        
        $branchData = FilmLayer::createBranchById($db, $projectId, $filmId,
            $userId, $gaUserId, FilmLayer::$INITIAL_BRANCH_NAME,
            FilmLayer::$INITIAL_BRANCH_DESCRIPTION, $accessTemplate);
        $branchId = $branchData[0];
        $changeId = $branchData[1];
        return array(
            $projectId,
            $filmId,
            $branchId,
            $changeId
        );
    }
    
    
    public static function doesBranchExist($db, $filmId, $branchName) {
        $data = VFilmBranchHead::$INSTANCE->countBy_Film_Id_x_Branch_Name($db,
                $filmId, $branchName);
        FilmLayer::checkError($data,
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
        FilmLayer::checkError($data,
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
        
        return FilmLayer::createBranchById($db, $projectId, $filmId, $userId,
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
            $accessTemplate = FilmLayer::$DEFAULT_TEMPLATE_ACCESS_NAME;
        }
        
        if (FilmLayer::doesBranchExist($db, $filmId, $branchName)) {
            throw new Base\ValidationException(
                array(
                    'Branch_Name' => 'A branch with that name already exists'
                ));
        }
        
        // FIXME ensure the branch name is valid
        // FIXME validate branch name length is valid
        
        // We first need a Gv Branch
        $data = GroboVersion\GvBranch::$INSTANCE->create($db, $projectId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the branch'
                )));
        $branchId = intval($data['result']);
        
        
        // Then the branch itself
        $branchItemId = GroboVersion\DataAccess::createItem($db);
        
        $data = FilmBranch::$INSTANCE->create($db, $branchId, $branchItemId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film branch'
                )));
        
        
        // Next set the permissions based on the template
        $data = FilmBranchAccess::$INSTANCE->runCreateFromTemplate($db,
                $branchId, $accessTemplate);
        FilmLayer::checkError($data,
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
        FilmLayer::updateBranchHeader($db, $branchId, $userId, $gaUserId,
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
     * Returns all the films.
     * The Paging object should be trusted, as it performs all the input
     * validation.
     *
     * @param PBO $db
     * @param Base\PageRequest $paging
     * @return array a page response json array.
     */
    public static function pageFilms($db, Base\PageRequest $paging = null) {
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                FilmLayer::$FILM_FILTERS, FilmLayer::$DEFAULT_FILM_SORT_COLUMN,
                FilmLayer::$FILM_SORT_COLUMNS);
        }
        $wheres = array(
            new Film_RestrictYear(
                $paging->filters[FilmLayer::$MIN_YEAR_SEARCH_FILTER->name],
                $paging->filters[FilmLayer::$MAX_YEAR_SEARCH_FILTER->name])
        );
        if ($paging->filters[FilmLayer::$NAME_SEARCH_FILTER->name] !== null) {
            $wheres[] = new Film_FuzzyName(
                '%' . $paging->filters[FilmLayer::$NAME_SEARCH_FILTER->name] .
                     '%');
        }
        

        $data = Film::$INSTANCE->readAll($db, $wheres, $paging->order,
            $paging->startRow, $paging->endRow);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the films'
                )));
        $rows = $data['result'];
        
        $data = Film::$INSTANCE->countAll($db, $wheres);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the films'
                )));
        $count = $data['result'];
        
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }
    
    
    /**
     *
     * @param PBO $db
     * @param int $filmId
     * @return the associative array with the film information, or null if it
     *      does not exist.
     */
    public static function getFilm($db, $filmId) {
        if (! is_integer($filmId)) {
            error_log("Expected integer, found [".$filmId."]");
            throw new Base\ValidationException(array(
                'film_id' => 'invalid film id format'
            ));
        }
        $filmId = intval($filmId);
        
        $data = Film::$INSTANCE->readBy_Film_Id($db, $filmId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the film'
                )));
        if (sizeof($data['result']) <= 0) {
            return array();
        }
        
        return $data['result'][0];
    }
    
    
    public static function updateFilm($db, $filmId, $name, $releaseYear) {
        if (! is_integer($filmId)) {
            error_log("Expected integer, found [".$filmId."]");
            throw new Base\ValidationException(array(
                            'film_id' => 'invalid film id format'
            ));
        }
        $filmId = intval($filmId);
        
        // See if there's anything to do
        $currentFilmData = FilmLayer::getFilm($db, $filmId);
        if ($currentFilmData['Name'] == $name &&
                $currentFilmData['Release_Year'] == $releaseYear) {
            return true;
        }
        
        // Ensure the film name/year don't already exist
        // There should already be a unique index on the table for these
        // two values.
        //if (FilmLayer::doesFilmExist($db, $name, $releaseYear)) {
        //    return false;
        //}
        
        $data = Film::$INSTANCE->update($db, $filmId, $name, $releaseYear);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem updating the film'
                )));
        return true;
    }
    
    
    public static function getLinksForFilm($db, $filmId) {
        if (! is_integer($filmId)) {
            throw new Base\ValidationException(array(
                            'film_id' => 'invalid film id format'
            ));
        }
        $filmId = intval($filmId);
        $data = VFilmLink::$INSTANCE->readBy_Film_Id($db, $filmId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the film links'
                )));
        // FIXME remove the linkTypeId - it's not needed by the clients,
        // and could be considered info leak.
        return $data['result'];
    }
    
    
    public static function saveLinkForFilm($db, $filmId, $linkName, $uri) {
        if (! is_integer($filmId)) {
            throw new Base\ValidationException(array(
                'film_id' => 'invalid film id format'
            ));
        }
        $filmId = intval($filmId);
        // quick existence check
        $data = Film::$INSTANCE->countBy_Film_Id($db, $filmId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the film'
                )));
        if ($data['result'] != 0) {
            new Base\ValidationException(
                array(
                    'filmId' => 'no film with that id'
                ));
        }
        
        $data = LinkType::$INSTANCE->readBy_Name($db, $linkName);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the link type'
                )));
        if ($data['rowcount'] != 1) {
            throw new Base\ValidationException(
                array(
                    'link type' => 'invalid link type name'
                ));
        }
        $regex = $data['result'][0]['Validation_Regex'];
        // test for both 0 and false
        if ($uri != null && ! preg_match('/'.$regex.'/', $uri)) {
            $matches = array();
            $ret = preg_match('/'.$regex.'/', $uri, $matches);
            error_log("bad match: return: ".($ret===false ? 'false' : $ret).", match: ".print_r($matches, true).", uri: ".$uri.", regex: ".$regex);
            throw new Base\ValidationException(
                array(
                    'uri' => 'uri does not match allowed patterns for this link type'
                ));
        }
        $linkId = $data['result'][0]['Link_Type_Id'];
        
        $data = FilmLink::$INSTANCE->upsert($db, $filmId, $linkId, $uri);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem inserting the uri'
                )));
        // This value doesn't really mean anything
        //if ($data['rowcount'] != 1) {
        //    error_log('upsert rowcount = '.$data['rowcount']);
        //    throw new Base\ValidationException(
        //        array(
        //            'link type' => 'could not update the link'
        //        ));
        //}
        return true;
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
                    FilmLayer::$BRANCH_FILTERS,
                    FilmLayer::$DEFAULT_BRANCH_SORT_COLUMN,
                    FilmLayer::$BRANCH_SORT_COLUMNS);
        }
        
        // We need a bit of dual logic here for the situation where the
        // user isn't logged in.
        
        $wheres = array();
        if ($paging->filters[FilmLayer::$NAME_SEARCH_FILTER->name] !== null) {
            $wheres[] = new VFilmBranchHead_BranchNameLike(
                    '%' . $paging->filters[FilmLayer::$NAME_SEARCH_FILTER->name] .
                    '%');
        }
        if ($paging->filters[FilmLayer::$DESCRIPTION_SEARCH_FILTER->name] !== null) {
            $wheres[] = new VFilmBranchHead_BranchDescriptionLike(
                    '%' . $paging->filters[FilmLayer::$DESCRIPTION_SEARCH_FILTER->name] .
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
        FilmLayer::checkError($rowData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branches'
                )));
        $rows = $rowData['result'];
        
        FilmLayer::checkError($countData,
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
            FilmLayer::checkError($data,
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
        
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the branches'
                )));
        
        return $data['result'] > 0;
    }


    public static function getHeadBranchVersion($db, $userId, $branchId) {
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = VFilmBranchHead::$INSTANCE->readBy_Gv_Branch_Id(
                 $db, $branchId);
        FilmLayer::checkError($rowData,
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
                    FilmLayer::$BRANCH_VERSION_FILTERS,
                    FilmLayer::$DEFAULT_BRANCH_VERSION_SORT_COLUMN,
                    FilmLayer::$BRANCH_VERSION_SORT_COLUMNS);
        }
        
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            return Base\PageResponse::createPageResponse($paging, 0, array());
        }
        
        $rowData = VFilmBranchVersion::$INSTANCE->readBy_Gv_Branch_Id(
                $db, $branchId,
                $paging->order, $paging->startRow, $paging->endRow);
        $countData = VFilmBranchVersion::$INSTANCE->countBy_Gv_Branch_Id(
                $db, $branchId);
        FilmLayer::checkError($rowData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the branch versions'
                )));
        $rows = $rowData['result'];
        
        FilmLayer::checkError($countData,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem counting the branches'
                )));
        $count = intval($countData['result'][0]);

        // Don't add the tags.
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
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
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
        
        FilmLayer::checkError($data,
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
        FilmLayer::checkError($data,
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
     */
    public static function updateBranchHeader($db, $branchId, $userId,
                $gaUserId, $newName, $newDescription, &$tagList,
                $checkAccess = true) {
        // userId CANNOT be null
        
        if ($userId === null || ($checkAccess &&
                ! FilmLayer::canAccessBranch($db, $userId, $branchId,
                        Access::$BRANCH_WRITE))) {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = FilmBranch::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        FilmLayer::checkError($data,
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
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the change version'
                )));
        
        FilmLayer::updateAllTagsOnBranch($db, $userId, $gaUserId,
                $branchChangeId, $tagList, false);
        
        GroboVersion\DataAccess::commitChange($db, $branchChangeId);
    }
    
    
    public static function updateAllTagsOnBranch($db, $userId, $gaUserId,
            $changeId, $tagList, $checkAccess) {
        // userId CANNOT be null
        
        if ($userId === null || ($checkAccess &&
                ! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_TAG))) {
            throw new Tonic\UnauthorizedException();
        }

        $tagMap = array();
        // Mark each tag as added.  This will be updated when we compare  this
        // list against what's in the database.
        foreach ($tagList as $tag) {
            $tag = normalizeTagName($tag);
            $tagMap[$tag] = true;
        }
        
        // Find the deltas on the tag list
        $data = VBranchTagHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId);
        FilmLayer::checkError($data,
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
                FilmLayer::updateTagOnBranch($db, $userId, $branchId,
                    $tag, $tagRow['Tag_Gv_Item_Id'], $changeId, true, false);
            }
        }
        
        // Add each new tag.
        foreach ($tagMap as $tag => $added) {
            if ($added) {
                FilmLayer::updateTagOnBranch($db, $userId, $branchId, $tag,
                    $tagId, $changeId, false, false);
            }
        }
    }
    
    
    public static function updateTagOnBranch($db, $userId, $branchId,
            $tagName, $tagId, $changeId, $removed, $checkAccess) {
        // userId CANNOT be null
        
        if ($checkAccess && (! $userId ||
                ! FilmLayer::canAccessBranch($db, $userId, $branchId,
            Access::$BRANCH_TAG))) {
                    throw new Tonic\UnauthorizedException();
        }
        
        // Normalize the tag name
        $tagName = normalizeTagName($tagName);
        
        if ($tagId === null) {
            // Find the tag's item ID.  If it doesn't exist, create it.
            $data = BranchTag::$INSTANCE->readBy_Name($db, $tagName);
            FilmLayer::checkError($data,
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
                $data = BrachTag::$INSTANCE->create($db, $tagId, $tagName);
                FilmLayer::checkError($data,
                    new Base\ValidationException(
                        array(
                            "Tags/$tag" => 'there was an unknown problem adding the branch tag'
                        )));
            }
        }
        
        // Add the item to the given change ID.
        GroboVersion\DataAccess::addItemToChange($db, $tagId, $changeId, $removed);
        
        // DO NOT submit the change.
    }
    
    
    public static function pageCommittedQuips($db, $userId, $branchId,
            $changeId, Base\PageRequest $paging = null) {
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            // This is a bit of a data leak
            throw new Tonic\UnauthorizedException();
        }
        
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    FilmLayer::$QUIP_FILTERS,
                    FilmLayer::$DEFAULT_QUIP_SORT_COLUMN,
                    FilmLayer::$QUIP_SORT_COLUMNS);
        }
        
        $wheres = array();
        
        // TODO No "where" support right now.  That will be checking the tags,
        // eventually
        
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the branch tags'
                )));
        
        if ($changeId <= 0) {
            // Get the head revision
            $rowData = VQuipHead::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                 $db, $branchId, $changeId);
            $countData = VQuipHead::$INSTANCE->countBy_Gv_Branch_Id_x_Gv_Change_Id(
                 $db, $branchId, $changeId);
        } else {
            $rowData = VQuipVersion::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                 $db, $branchId, $changeId);
            $countData = VQuipVersion::$INSTANCE->countBy_Gv_Branch_Id_x_Gv_Change_Id(
                 $db, $branchId, $changeId);
        }
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the branch tags'
                )));
        
        $rows = $rowData['result'];
        $count = $countData['result'];
        
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }


    public static function pageCommittedPendingQuips($db, $userId, $branchId,
            $changeId, Base\PageRequest $paging = null) {
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            // This is a bit of a data leak
            throw new Tonic\UnauthorizedException();
        }
    
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    FilmLayer::$QUIP_FILTERS,
                    FilmLayer::$DEFAULT_QUIP_SORT_COLUMN,
                    FilmLayer::$QUIP_SORT_COLUMNS);
        }
    
        $wheres = array();
    
        // TODO No "where" support right now.  That will be checking the tags,
        // eventually
    
        FilmLayer::checkError($data,
        new Base\ValidationException(
        array(
        'unknown' => 'there was an unknown problem reading the branch tags'
                )));
    
        if ($changeId <= 0) {
            // Get the head revision
            $rowData = VQuipHead::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                    $db, $branchId, $changeId);
            $countData = VQuipHead::$INSTANCE->countBy_Gv_Branch_Id_x_Gv_Change_Id(
                    $db, $branchId, $changeId);
        } else {
            $rowData = VQuipVersion::$INSTANCE->readBy_Gv_Branch_Id_x_Gv_Change_Id(
                    $db, $branchId, $changeId);
            $countData = VQuipVersion::$INSTANCE->countBy_Gv_Branch_Id_x_Gv_Change_Id(
                    $db, $branchId, $changeId);
        }
        FilmLayer::checkError($data,
        new Base\ValidationException(
        array(
        'unknown' => 'there was an unknown problem reading the branch tags'
                )));
    
        $rows = $rowData['result'];
        $count = $countData['result'];
    
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }
    
    
    

    // ----------------------------------------------------------------------
    
    
    // Clean tag name so it can be added or match existing tags:
    //  - trim whitespace at the start and end of the string.
    //  - replace connected whitespace in the middle with a single underscore
    //  - lowercase it.
    private static function normalizeTagName($tagName) {
        return strtolower(preg_replace('/\s+/', '_', trim($tagName)));
    }
    
    
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}
FilmLayer::$FILM_SORT_COLUMNS = array(
    "name" => "Name",
    "year" => "Release_Year",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

FilmLayer::$MIN_YEAR_SEARCH_FILTER =
    new Base\SearchFilterInt("yearMin", 1800, 1800, 9999);
FilmLayer::$MAX_YEAR_SEARCH_FILTER =
    new Base\SearchFilterInt("yearMax", 9999, 1800, 9999);
FilmLayer::$NAME_SEARCH_FILTER =
    new Base\SearchFilterString("name", null);
FilmLayer::$DESCRIPTION_SEARCH_FILTER =
    new Base\SearchFilterString("description", null);


FilmLayer::$FILM_FILTERS = array(
    FilmLayer::$MIN_YEAR_SEARCH_FILTER,
    FilmLayer::$MAX_YEAR_SEARCH_FILTER,
    FilmLayer::$NAME_SEARCH_FILTER
);



FilmLayer::$BRANCH_SORT_COLUMNS = array(
    "name" => "Branch_Name",
    "description" => "Description",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

FilmLayer::$BRANCH_FILTERS = array(
    FilmLayer::$NAME_SEARCH_FILTER,
    FilmLayer::$DESCRIPTION_SEARCH_FILTER
);


FilmLayer::$BRANCH_VERSION_SORT_COLUMNS = array(
    "change" => "Gv_Change_Id",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

FilmLayer::$BRANCH_VERSION_FILTERS = array(
);



FilmLayer::$QUIP_SORT_COLUMNS = array(
    "timestamp" => "Timestamp_Millis"
            
    // FIXME eventually this will add tags to the filters.
);

FilmLayer::$QUIP_FILTERS = array();
