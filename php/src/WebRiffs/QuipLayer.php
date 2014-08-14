<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;
use GroboVersion;


/**
 * Manages the business logic related to the branches.
 */
class QuipLayer {
    public static $QUIP_SORT_COLUMNS;
    public static $DEFAULT_QUIP_SORT_COLUMN = 'timestamp';
    public static $QUIP_FILTERS;
    
    /**
     * Maximum number of tags allowable on a quip
     *
     * @var int
     */
    public static $MAXIMUM_TAG_COUNT = 20;
    
    
    
    
    /**
     * Find the changes that have happened between the user's pending
     * version and the current head version.  These are changes on the
     * quips for the branch, not the branch header.
     */
    public static function getBranchQuipChangesFromPending($db, $userId,
                $branchId) {
        // FIXME
        
        // First, load from USER_BRANCH_PENDING_VERSION
        // to see if the user even has an entry for the branch (this acts as
        // access control, implicitly).
        // If there is a result, use it to
        // Load from V_QUIP_CHANGE
        
        // Need to augment the returned list to limit the number of row results,
        // and to describe how many changes have happened to the branch
        // since the user pending was created.
    }
    
    
    // FIXME need a function to return how many branches, and which ones,
    // the user has pending changes on.
    
    
    public static function pageCommittedQuips($db, $userId, $branchId,
            $changeId, Base\PageRequest $paging = null) {
        if (! BranchLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            // This is a bit of a data leak - we let people know that  this
            // user id is not authorized to see this branch, and, thus, lets
            // the user know that the branch ID exists.
            throw new Tonic\UnauthorizedException();
        }
        
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    QuipLayer::$QUIP_FILTERS,
                    QuipLayer::$DEFAULT_QUIP_SORT_COLUMN,
                    QuipLayer::$QUIP_SORT_COLUMNS);
        }
        
        $wheres = array();
        
        // TODO No "where" support right now.  That will be checking the tags,
        // eventually
        
        QuipLayer::checkError($data,
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
        QuipLayer::checkError($data,
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
        if (! QuipLayer::canAccessBranch($db, $userId, $branchId,
                Access::$BRANCH_READ)) {
            // This is a bit of a data leak
            throw new Tonic\UnauthorizedException();
        }
    
        if ($paging == null) {
            $paging = Base\PageRequest::parseGetRequest(
                    QuipLayer::$QUIP_FILTERS,
                    QuipLayer::$DEFAULT_QUIP_SORT_COLUMN,
                    QuipLayer::$QUIP_SORT_COLUMNS);
        }
    
        $wheres = array();
    
        // TODO No "where" support right now.  That will be checking the tags,
        // eventually
    
        QuipLayer::checkError($data,
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
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem reading the branch tags'
                )));
    
        $rows = $rowData['result'];
        $count = $countData['result'];
    
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
    }
    
    
    

    // ----------------------------------------------------------------------
    
    
    public static function normalizeTagName($tagName) {
        return BranchLayer::normalizeTagName($tagName);
    }
    
    
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}
QuipLayer::$QUIP_SORT_COLUMNS = array(
    "timestamp" => "Timestamp_Millis"
            
    // FIXME eventually this will add tags to the filters.
);

QuipLayer::$QUIP_FILTERS = array();
