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
    public static $INITIAL_BRANCH_NAME = "Initial";
    public static $INITIAL_BRANCH_DESCRIPTION = "First branch for the film";
    
    public static $FILM_SORT_COLUMNS;
    public static $DEFAULT_FILM_SORT_COLUMN = 'name';
    public static $FILM_FILTERS;

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
        
        $branchData = BranchLayer::createBranchById($db, $projectId, $filmId,
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
        // remove the linkTypeId - it's not needed by the clients,
        // and could be considered info leak.
        $rows = $data['result'];
        foreach ($rows as &$row) {
            unset($row['Link_Type_Id']);
            $row['Is_Playback_Media'] =
                ($row['Is_Playback_Media'] == 0) ? FALSE : TRUE;
        }
        
        return $rows;
    }
    
    
    public static function saveLinkForFilm($db, $filmId, $linkName, $uri,
            $isPlaybackMedia) {
        $errors = array();
        if (! is_integer($filmId)) {
            $errors['film_id'] = 'invalid film id format';
        }
        if (! is_bool($isPlaybackMedia)) {
            $errors['is_playback_media'] = 'invalid is_playback_media';
        }
        // the $linkName is checked in the call to fetch that link.
        if (! is_string($uri) || strlen($uri) <= 0 || strlen($uri) > 300) {
            $errors['uri'] = 'invalid uri string';
        }
        if (sizeof($errors) > 0) {
            throw new Base\ValidationException($errors);
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
        
        $link = AdminLayer::getLinkNamed($db, $linkName);
        if ($isPlaybackMedia && ! $link['Is_Media']) {
            throw new Base\ValidationException(
                array(
                    'is_playback_media' => 'link is not a media type, so the film cannot use it as a media source'
                ));
        }
        
        
        $regex = $link['Validation_Regex'];
        // test for both 0 and false
        if ($uri != null && ! preg_match('/'.$regex.'/', $uri)) {
            $matches = array();
            $ret = preg_match('/'.$regex.'/', $uri, $matches);
            error_log("bad match: return: ".
                    ($ret===false ? 'false' : $ret).
                    ", match: ".print_r($matches, true).
                    ", uri: ".$uri.", regex: ".$regex);
            throw new Base\ValidationException(
                array(
                    'uri' => 'uri does not match allowed patterns for this link type'
                ));
        }
        $linkId = $link['Link_Type_Id'];
        
        $data = FilmLink::$INSTANCE->upsert($db, $filmId, $linkId,
                $isPlaybackMedia, $uri);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem inserting the uri'
                )));
        // The key for this object doesn't really mean anything
        //if ($data['rowcount'] != 1) {
        //    error_log('upsert rowcount = '.$data['rowcount']);
        //    throw new Base\ValidationException(
        //        array(
        //            'link type' => 'could not update the link'
        //        ));
        //}
        return true;
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


FilmLayer::$FILM_FILTERS = array(
    FilmLayer::$MIN_YEAR_SEARCH_FILTER,
    FilmLayer::$MAX_YEAR_SEARCH_FILTER,
    FilmLayer::$NAME_SEARCH_FILTER
);

