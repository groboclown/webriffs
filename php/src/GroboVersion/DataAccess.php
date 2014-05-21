<?php

namespace GroboVersion;

use PBO;
use Base;

// The code references the GA_USER table, but that isn't directly used here.

/**
 * Usage notes:
 *
 * Projects are only for the caller to use as a reference to their own
 * objects; there's no reason (other than basic admin cleanup) to list them.
 *
 * @author Groboclown
 *
 */
class DataAccess {
    /**
     * Create a new project.  This will not create the initial branch for the
     * project; it must be created separately.
     *
     * @param PBO $db
     * @return int the created project id
     */
    public static function createProject($db) {
        $projectId = intval(GvProject::$INSTANCE->create($db));
        DataAccess::checkError(GvProject::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem accessing the project'
            )));
        return $projectId;
    }
    
    
    /**
     * Removing a project is a heavyweight operation.
     *
     * @param PBO $db
     * @return boolean true if the delete succeeded.
     */
    public static function deleteProject($db, $projectId) {
        // This will usually fail horribly
        $deletedCount = intval(GvProject::$INSTANCE->remove($db, $projectId));
        DataAccess::checkError(GvProject::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem removing the project'
            )));
        return $deletedCount <= 0;
    }
    
    
    /**
     * Create a new branch for a project based on another branch.
     *
     * If the parent branch id is null, then this will not have a parent.
     *
     * Branches should not be directly queried, but instead referenced through
     * the real object model.
     *
     * @param PBO $db
     * @param int $projectId
     * @param int $parentBranchId
     * @return int branch ID
     */
    public static function createBranch($db, $projectId, $parentBranchId) {
        $id = intval(GvBranch::$INSTANCE->create($db, $projectId));
        DataAccess::checkError(GvBranch::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating a branch'
            )));
        if ($parentBranchId !== null) {
            DataAccess::branchTo($db, $id, $parentBranchId, null);
        }
        return $id;
    }
    
    
    /**
     * Branch the version of the parent (or head if null) into the child
     * branch.
     *
     * @param PBO $db
     * @param int $gaUserId user creating the branch
     * @param int $targetBranchId
     * @param int $parentBranchId
     * @param int $targetChangeId
     * @param int $sourceChangeId may be null if the head version should be
     *      used.
     * @return int $branchHistoryId
     */
    public static function branchTo($db, $gaUserId,
            $targetBranchId, $sourceBranchId,
            $targetChangeId, $sourceChangeId = null) {
        if ($sourceChangeId === null) {
            $sourceChangeId = DataAccess::getHeadChange($db,
                    $sourceBranchId);
        }
        
        if (DataAccess::isChangeCommitted($db, $targetChangeId)) {
            throw new Exception("can only branch into a pending change");
        }
        
        // Record the branch in the history
        $branchHistoryId = intval(GvBranchHistory::$INSTANCE->create(
                $db, $gaUserId, $targetBranchId, $sourceBranchId,
                $targetChangeId, $sourceVersionId));
        DataAccess::checkError(GvBranchHistory::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating the branch history'
            )));
            
        // FIXME move this into the generator, so that the mock class works
        // correctly.
        // Special sql for speed.  All the changes in the source branch
        // at that version are put into the target branch.
        
        //  FIXME ensure the item versions are not already in the branch.
        
        $stmt = $db->prepare("INSERT INTO GV_CHANGE_VERSION (".
                "Gv_Item_Version_Id, Gv_Change_Id, Created_On, Last_Updated_On".
                ") SELECT Gv_Item_Version_Id, Gv_Change_Id, NOW(), NULL FROM ".
                "V_GV_CHANGE_ITEM WHERE Gv_Branch_Id = :Gv_Branch_Id AND ".
                "Gv_Change_Id = :Gv_Change_Id");
        $data = array(
                "Gv_Change_Id" => $sourceVersionId,
                "Gv_Branch_Id" => $sourceBranchId,
        );
        $res = $stmt->execute($data);
        $errs = $db->errorInfo();
        if ($errs[1] !== null) {
            GvChangeVersion::$INSTANCE->errors[] = $errs[2];
            GvChangeVersion::$INSTANCE->errnos[] = $errs[1];
            DataAccess::checkError(GvChangeVersion::$INSTANCE,
                new Base\ValidationException(array(
                    'unknown' => 'there was an unknown problem branching'
                )));
        }
        return $branchHistoryId;
    }
    
    
    public static function getHeadChange($db, $branchId) {
        $ret = VGvBranchHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId,
            // Order largest to smallest
            'Gv_Change_Id DESC');
        DataAccess::checkError(VGvBranchHead::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem retrieving the version'
            )));
        if (sizeof($ret) <= 0) {
            return null;
        }
        if (sizeof($ret) > 1) {
            error_log("Bad view for branch head - it should return at most ".
                    "1 row per branch.");
            // but allow it
        }
        return intval($ret[0]['Gv_Change_Id']);
    }
    
    
    /**
     *
     * @param unknown $db
     */
    public static function createItem($db) {
        $id = GvItem::$INSTANCE->create($db);
        DataAccess::checkError(GvChange::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating an item'
            )));
        return intval($id);
    }
    
    
    /**
     * Creates a new version for the item, and adds it to the change.
     *
     * @param PBO $db
     * @param int $itemId
     * @param int $changeId
     * @param boolean $deleted true if the item is being deleted, false
     *      (default) otherwise.
     * @return array(int, int) the new item version ([0]), and the change version
     *      id ([1]).
     */
    public static function addItemToChange($db, $itemId, $changeId,
            $deleted = false) {
        if (DataAccess::isChangeCommitted($db, $changeId)) {
            throw new Exception("can only add items to pending changes");
        }
        
        // FIXME ensure the item is not already in the change.
        
        if (!! $deleted) {
            $alive = 0;
        } else {
            $alive = 1;
        }
        
        $ivId = intval(GvItemVersion::$INSTANCE->create($db, $itemId, $alive));
        DataAccess::checkError(GvItemVersion::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating the version'
            )));
        $cvId = intval(GvChangeVersion::$INSTANCE->create($db, $ivId,
                $changeId));
        DataAccess::checkError(GvChangeVersion::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating the version'
            )));
        return array($ivId, $cvId);
    }
    
    
    public static function isChangeCommitted($db, $changeId) {
        $data = GvChange::$INSTANCE->readBy_Gv_Change_Id($db, $changeId);
        DataAccess::checkError(GvChangeVersion::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating the version'
            )));
        if (sizeof($data) <= 0) {
            // might lead to leaking information
            throw new Exception("unknown change id ".$changeId);
        }
        if (sizeof($data) > 1) {
            // multiple change IDs - should be impossible
            error_log("found multiple committed changes with id ".$changeId);
            // but keep going
        }
        return $data[0]['Active_State'] == 1 ? true : false;
    }
    
    
    /**
     * Create an (uncommitted) change.
     *
     * @param unknown $db
     * @param unknown $branchId
     * @param unknown $gaUserId
     * @return int the change ID
     */
    public static function createChange($db, $branchId, $gaUserId) {
        $ret = GvChange::$INSTANCE->create($db, $branchId, 0, $gaUserId);
        DataAccess::checkError(GvChange::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem creating a change'
            )));
        return intval($ret);
    }
    
    
    public static function countItemsInChange($db, $changeId) {
        $count = GvChangeVersion::$INSTANCE->countBy_Gv_Change_Id($db,
                $changeId);
        DataAccess::checkError(GvChangeVersion::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem searching the change'
            )));
        return intval($count);
    }
    
    
    /**
     * Returns all the items in the change.
     *
     * @param unknown $db
     * @param unknown $changeId
     * @return array() of rows, with each row containing elements
     *      Gv_Change_Version_Id,
     *      Gv_Item_Version_Id, Gv_Change_Id, Created_On, Last_Updated_On
     */
    public static function getItemsInChange($db, $changeId, $rowStart, $rowEnd) {
        $data = GvChangeVersion::$INSTANCE->readBy_Gv_Change_Id($db, $changeId,
                $rowStart, $rowEnd);
        DataAccess::checkError(GvChangeVersion::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem searching the change'
            )));
        return $data;
    }
    
    
    public static function countPendingChangesForUser($db, $gaUserId,
            $branchId) {
        $count = VGvPendingChange::$INSTANCE->countBy_Gv_Branch_Id_x_Ga_User_Id(
             $db, $branchId, $gaUserId);
        DataAccess::checkError(VGvPendingChange::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem searching the changes'
            )));
        return intval($count);
    }
    
    
    /**
     * Returns the pending changes for the user on the given branch.
     *
     * @param unknown $db
     * @param unknown $gaUserId
     * @param unknown $branchId
     */
    public static function getPendingChangesForUser($db, $gaUserId, $branchId,
            $rowStart, $rowEnd) {
        $data = VGvPendingChange::$INSTANCE->readBy_Gv_Branch_Id_x_Ga_User_Id(
                $db, $branchId, $gaUserId, $rowStart, $rowEnd);
        DataAccess::checkError(VGvPendingChange::$INSTANCE,
            new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem searching the changes'
            )));
        return $data;

    }
    
    
    
    
    // ----------------------------------------------------------------------
    
    
    private static function checkError($errorSource, $exception) {
        if (sizeof($errorSource->errors) > 0) {
            $backtrace = 'Database access error (['.
                implode('], [', $errorSource->errors).']):';
            foreach (debug_backtrace() as $stack) {
                $backtrace .= '\n    '.$stack['function'].'('.
                    implode(', ', $stack['args']).') ['.
                    $stack['file'].' @ '.$stack['line'].']';
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


