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
    
    
    public static $FILM_SORT_COLUMNS;
    public static $DEFAULT_FILM_SORT_COLUMN = "name";
    public static $FILM_FILTERS;
    
    public static $BRANCH_SORT_COLUMNS;
    public static $DEFAULT_BRANCH_SORT_COLUMN = 'name';
    public static $BRANCH_FILTERS;

    public static $MIN_YEAR_SEARCH_FILTER;
    public static $MAX_YEAR_SEARCH_FILTER;
    public static $NAME_SEARCH_FILTER;
    
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
            $gaUserId, FilmLayer::$INITIAL_BRANCH_NAME,
            $accessTemplate);
        $branchId = $branchData[0];
        $filmBranchId = $branchData[1];
        $changeId = $branchData[2];
        return array(
            $projectId,
            $filmId,
            $branchId,
            $filmBranchId,
            $changeId
        );
    }
    
    
    public static function doesBranchExist($db, $filmId, $branchName) {
        $data = VFilmBranch::$INSTANCE->countBy_Film_Id_x_Branch_Name($db,
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
    public static function createBranch($db, $filmId, $gaUserId,
            $branchName, $accessTemplate) {
        
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
                'filmid' => 'no film with that ID exists'
            ));
        }
        
        $projectId = $data['result'][0]['Gv_Project_Id'];
        
        return FilmLayer::createBranchById($db, $projectId, $filmId, $gaUserId,
                $branchName, $accessTemplate);
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
    public static function createBranchById($db, $projectId, $filmId,
            $gaUserId, $branchName, $accessTemplate) {
        
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
        $data = FilmBranch::$INSTANCE->create($db, $branchId, $branchName);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film branch'
                )));
        $filmBranchId = intval($data['result']);
        
        
        // Next set the permissions based on the template
        $data = FilmBranchAccess::$INSTANCE->runCreateFromTemplate($db,
                $filmBranchId, $accessTemplate);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the access permissions'
                )));
        
        // Finally a change for the user to start using.
        $data = GroboVersion\GvChange::$INSTANCE->create(
                $db, $branchId, 0, $gaUserId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the change'
                )));
        $changeId = intval($data['result']);
        return array(
            $branchId,
            $filmBranchId,
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
     * Returns all the branches in the film that are visible by the user.
     * Currently there are no filters on branch names.
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
        
        if ($userId === null) {
            $wheres = array(new VFilmBranchGuestAccess_IsAllowed(
                    Access::$PRIVILEGE_GUEST, Access::$BRANCH_READ
                ));
            
            $rowData = VFilmBranchGuestAccess::$INSTANCE->readBy_Film_Id(
                    $db, $filmId, $wheres,
                    $paging->order, $paging->startRow, $paging->endRow);
            $countData = VFilmBranchGuestAccess::$INSTANCE->countBy_Film_Id(
                    $db, $filmId, $wheres);
        } else {
            $wheres = array(new VFilmBranchAccess_IsAllowed());
            
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
            $wheres = array(new FilmBranchAccess_IsGuestAllowed(
                    $access, Access::$PRIVILEGE_GUEST));
            $data = FilmBranchAccess::$INSTANCE->countBy_Film_Branch_Id(
                    $db, $branchId, $wheres);
        } else {
            // logged-in user
            $wheres = array(new VFilmBranchAccess_IsAllowed());
            $data = VFilmBranchAccess::$INSTANCE->countBy_Film_Branch_Id_x_User_Id_x_Access(
                    $db, $branchId, $userId, $access, $wheres);
        }
        
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the branches'
                )));
        
        return $data['result'] > 0;
    }


    /**
     * Returns all the tags in the branch.  If the user can't see the branch,
     * then they can't see any tags.  The number of tags for a branch should be
     * limited, so this will not perform paging.
     */
    public static function getTagsForBranch($db, $userId, $filmId, $branchId) {
        // userId can be null
        
        if (! FilmLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            // not a visible branch
            return array();
        }
        
        $data = VFilmBranchTag::$INSTANCE->readBy_Film_Branch_Id(
                $db, $branchId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the tags'
                )));
        $rows = $data['result'];
        
        return $rows;
    }
    

    // ----------------------------------------------------------------------
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

FilmLayer::$DEFAULT_FILM_SORT_COLUMN = "name";

FilmLayer::$FILM_FILTERS = array(
    FilmLayer::$MIN_YEAR_SEARCH_FILTER,
    FilmLayer::$MAX_YEAR_SEARCH_FILTER,
    FilmLayer::$NAME_SEARCH_FILTER
);

FilmLayer::$BRANCH_SORT_COLUMNS = array(
    "name" => "Branch_Name",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

FilmLayer::$DEFAULT_BRANCH_SORT_COLUMN = "name";

FilmLayer::$BRANCH_FILTERS = array(
    FilmLayer::$NAME_SEARCH_FILTER
);
