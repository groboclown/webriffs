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
    public static $FILM_SORT_COLUMNS;
    public static $DEFAULT_SORT_COLUMN = "name";
    public static $MIN_YEAR_SEARCH_FILTER;
    public static $MAX_YEAR_SEARCH_FILTER;
    public static $NAME_SEARCH_FILTER;
    public static $FILTERS;


    /**
     * Creates the film, and adds a new, empty film version.
     *
     * @param
     *            array userInfo must be the user session, so we don't need to
     *            check its value.
     * @return array contains the (projectId, filmId, branchId, changeId)
     */
    public static function createFilm($db, $userInfo, $name, $year, $imdbUrl,
        $wikipediaUrl) {
        
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
        if (!is_integer($year) || $year < 0 || $year > 9999) {
            throw new Base\ValidationException(
                array(
                    "year" => "invalid year"
                ));
        }
        
        // ensure the URLs are relative (and are valid
        // urls within that host
        


        if (!preg_match('/^\\/title\\/[a-zA-Z0-9]+\\/?$/', $imdbUrl)) {
            throw new Base\ValidationException(
                array(
                    "imdbUrl" => "invalid format; must be relative and in the form '/title/[id]/'"
                ));
        }
        
        if (!preg_match('/^\\/wiki\\/[a-zA-Z0-9\\$_-\\(\\)]+$/', $wikipediaUrl)) {
            throw new Base\ValidationException(
                array(
                    "wikipediaUrl" => "invalid format; must be relative and in the form '/wiki/[page_title]'"
                ));
        }
        
        if (!$userInfo || !is_array($userInfo)) {
            throw new Base\ValidationException(
                array(
                    "user" => "user does not not have sufficient permissions"
                ));
        }
        
        $userId = $userInfo['User_Id'];
        $gaUserId = $userId['Ga_User_Id'];
        // FIXME need better permission checks.
        


        // We'll first check if the name/release year are already taken.
        // This isn't a guarantee no one will take it between here and the
        // create statement, though.  The film table has a unique
        // constraint on the year+name, so that will prevent the duplicate
        // creation, but the project will still exist.  We can live with that.
        


        $data = Film::$INSTANCE->readBy_Name_x_Release_Year($db, $name, $year);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem checking the films'
                )));
        if (sizeof($data['result']) > 0) {
            throw new Base\ValidationException(
                array(
                    'name,year' => 'the name and release year already exist'
                ));
        }
        
        $data = GvProject::$INSTANCE->create($db);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem setting up the versioning'
                )));
        $projectId = intval($data['result']);
        
        $data = Film::$INSTANCE->create($db, $projectId, $name, $year, $imdbUrl,
            $wikipediaUrl);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film record'
                )));
        $filmId = intval($data['result']);
        
        $branchData = FilmLayer::createBranchById($db, $projectId, $filmId,
            "Initial");
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
    public static function createBranchById($db, int $projectId, int $filmId,
        int $gaUserId, string $branchName) {
        // FIXME ensure the branch name does not exist for that film id.
        // FIXME ensure the branch name is valid
        
        $data = GvBranch::$INSTANCE->create($db, $projectId);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the branch'
                )));
        $branchId = intval($data['result']);
        
        
        $data = FilmBranch::$INSTANCE->create($db, $branchId, $branchName);
        FilmLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the film branch'
                )));
        $filmBranchId = intval($data['result']);
        
        
        $data = GvChange::$INSTANCE->create($db, $branchId, 0, $gaUserId);
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
    public static function pageFilms($db, Base\PageRequest $paging) {
        $wheres = array(
            new Film\Film_Film_RestrictYear(
                $paging->filters[FilmLayers::$MIN_YEAR_SEARCH_FILTER->name],
                $paging->filters[FilmLayers::$MAX_YEAR_SEARCH_FILTER->name])
        );
        if ($paging->filters[FilmLayers::$NAME_SEARCH_FILTER->name] !== null) {
            $wheres[] = new Film\Film_FuzzyName(
                '%' . $paging->filters[FilmLayers::$NAME_SEARCH_FILTER->name] .
                     '%');
        }
        

        $data = Film::$INSTANCE->readAll($db, $wheres, $order, $startRow,
            $endRow);
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
     * Returns all the films with branches that are visible by the user.
     */
    public static function findFilmsAndBranches($db, $userId, $sortBy, $rowCount) {
        
        // FIXME
    }
    

    // ----------------------------------------------------------------------
    private static function checkError($returned, $exception) {
        if ($returned["haserror"]) {
            $backtrace = 'Database access error (' . $returned["errorcode"] . ' ' .
                 $returned["error"] . '):';
            foreach (debug_backtrace() as $stack) {
                $backtrace .= '\n    ' . $stack['function'] . '(' .
                     implode(', ', $stack['args']) . ') [' . $stack['file'] .
                     ' @ ' . $stack['line'] . ']';
            }
            error_log($backtrace);
            
            // TODO make the error messages language agnostic.
            


            // can have special logic for the $errorSource->errnos
            // error codes, to have friendlier messages.
            


            // 1062: already in use.
            


            throw $exception;
        }
    }
}
FilmLayer::$FILM_SORT_COLUMNS = array(
    "name" => "Name",
    "year" => "Release_Year",
    "created" => "Created_On",
    "updated" => "Last_Updated_On"
);

FilmLayer::$DEFAULT_SORT_COLUMN = "name";

FilmLayer::$MIN_YEAR_SEARCH_FILTER = new Base\SearchFilterInt("yearMin", 0, 0,
    9999);
FilmLayer::$MAX_YEAR_SEARCH_FILTER = new Base\SearchFilterInt("yearMax", 9999, 0,
    9999);

FilmLayer::$NAME_SEARCH_FILTER = new Base\SearchFilterString("name", null);

FilmLayer::$FILTERS = array(
    FilmLayers::$MIN_YEAR_SEARCH_FILTER,
    FilmLayers::$MAX_YEAR_SEARCH_FILTER,
    FilmLayers::$NAME_SEARCH_FILTER
);
