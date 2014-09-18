<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;
use GroboVersion;

require_once 'BranchLayer.php';

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
        // Load from V_QUIP_CHANGE.
        
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
    
    
    /**
     * Create a pending change on a branch.
     *
     * @param unknown $db
     * @param int $userId
     * @param int $gaUserId
     * @param int $branchId
     * @param int $baseChangeId
     * @param bool $checkAccess
     * @throws Tonic\UnauthorizedException
     */
    public static function createPendingChange($db, $userId, $gaUserId,
            $branchId, $baseChangeId, $checkAccess = true) {
        // userId CANNOT be null
        if ($userId === null || ($checkAccess && (
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
                        Access::$QUIP_WRITE) ||
                ! BranchLayer::canAccessBranch($db, $userId, $branchId,
                        Access::$QUIP_TAG)))) {
            throw new Tonic\UnauthorizedException();
        }
        
        // ensure the branch exists
        $data = FilmBranch::$INSTANCE->countBy_Gv_Branch_Id($db, $branchId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem finding the branch'
                )));
        if ($data['result'] <= 0) {
            throw new Base\ValidationException(array(
                'branchId' => 'branch does not exist'
            ));
        }
        
        // User cannot have an existing pending change on this branch.
        // However, multiple calls to this function shouldn't cause an
        // error.
        $data = UserBranchPendingVersion::$INSTANCE->readBy_User_Id_x_Gv_Branch_Id($db,
                $userId, $branchId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem fetching the pending change'
                )));
        if ($data['rowcount'] > 1 || $data['rowcount'] < 0) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'data integrety issue on pending changes'
                ));
        }
        if ($data['rowcount'] == 1) {
            if ($data['result'][0]['Pending_Change_Id'] <= 1) {
                // Change is already pending
                throw new Tonic\ConditionException();
            }
            // Return the existing object.
            return array($data['result'][0]['Pending_Change_Id'],
                $data['result'][0]['Base_Change_Id']);
        }
        
        // Create the initial placeholder for the change.  This will be updated
        // later.  This allows us to avoid adding a pending change that is
        // never used in the case of multiple async calls here.  This avoids
        // the need to lock the db.
        
        // Uses a fake pending change id "1", because that should only be
        // for the very first branch's very first change, which is the header
        // details.  It should never be a quip version.  We don't want to use
        // something else, because that could generate a foreign key constraint
        // problem.
        
        // Note: if foreign key constraints are in place, then the above code
        // to check if the branch exists should be unnecessary.
        $data = UserBranchPendingVersion::$INSTANCE->create($db,
                $userId, $branchId, $baseChangeId, 1);
        if ($data['haserror'] && $data['errorcode'] == 1062) {
            // duplicate key
            error_log("Duplicate key: user has 2 in-progress requests, or something went horribly wrong.");
            throw new Tonic\ConditionException();
        }
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem creating a pending change'
                )));
        
        // Create the change object
        $pendingChangeId = GroboVersion\DataAccess::createChange($db,
                $branchId, $gaUserId);
        
        // Update the pending version to use the real pending change id
        $data = UserBranchPendingVersion::$INSTANCE->upsert($db,
                $userId, $branchId, $baseChangeId, $pendingChangeId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem creating a pending change'
                )));
        
        return array($pendingChangeId, $baseChangeId);
    }
    
    
    public static function commitPendingChange($db, $userId, $gaUserId,
            $branchId) {
        $data = UserBranchPendingVersion::$INSTANCE->readBy_User_Id_x_Gv_Branch_Id($db,
                $userId, $branchId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem fetching the pending change'
                )));
        if ($data['rowcount'] > 1 || $data['rowcount'] < 0) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'data integrety issue on pending changes'
                ));
        }
        if ($data['rowcount'] == 0) {
            throw new Base\ValidationException(
                array(
                    'unknown' => 'no pending change for user on branch'
                ));
        }
        $changeId = $data['result'][0]['Pending_Change_Id'];
        
        $finalChangeId = GroboVersion\DataAccess::commitChange($db, $changeId,
            $gaUserId);
        
        $data = UserBranchPendingVersion::$INSTANCE->remove($db,
            $userId, $branchId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem removing the pending change'
                )));
        return $finalChangeId;
    }
    
    
    /**
     * Remove the pending change for the user on the branch.
     *
     * @param unknown $db
     * @param unknown $userId
     * @param unknown $gaUserId
     * @param unknown $branchId
     */
    public static function deletePendingChange($db, $userId, $gaUserId,
            $branchId) {
        
        // This will delete all the pending changes, regardless of how many
        // pending changes are assigned to that user on that branch.
        // (> 1 is a data integrety error, but it's handled correctly)
        
        $data = UserBranchPendingVersion::$INSTANCE->remove($db, $userId, $branchId);
        QuipLayer::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'problem removing the pending version'
                )));
        GroboVersion\DataAccess::deletePendingChangesForUserBranch($db,
            $gaUserId, $branchId);
    }
    
    
    public static function saveQuip($db, $userId, $gaUserId, $branchId,
            $quipId, $quipText, &$tags) {
        // FIXME
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
