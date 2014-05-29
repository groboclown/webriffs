<?php

namespace GroboVersion;

use PBO;
use Base;

// The code references the GA_USER table, but that isn't directly used here.


/**
 *
 * @author Groboclown
 *
 */
class DataAccess {


    /**
     * Create a new project.
     * This will not create the initial branch for the
     * project; it must be created separately.
     *
     * @param PBO $db
     * @return int the created project id
     */
    public static function createProject($db) {
        $data = GvProject::$INSTANCE->create($db);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem accessing the project'
                )));
        $projectId = intval($data['result']);
        return $projectId;
    }


    /**
     *
     * @param PBO $db
     * @return int the number of projects
     */
    public static function getProjectCount($db) {
        $data = GvProject::$INSTANCE->countAll($db);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem accessing the projects'
                )));
        $count = intval($data['result']);
        return $count;
    }


    /**
     * Returns the list of the projects in the db.
     * This should be only used
     * for administrative purposes, as the actual IDs should be associated with
     * the corresponding business object.
     *
     * @param unknown $db
     * @param int $start
     * @param int $end
     * @return array(rows) all the projects. Each project is an array with
     *         the keys
     *         [int Gv_Project_Id, string Created_On, string Last_Updated_On]
     */
    public static function getProjects($db, $start, $end) {
        if (!is_integer($start) || !is_integer($end)) {
            throw new Base\ValidationException(
                array(
                    "start" => $start,
                    "end" => $end
                ));
        }
        $data = GvProject::$INSTANCE->readAll($db, false, $start, $end);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem accessing the projects'
                )));
        return $data['result'];
    }


    /**
     * Removing a project is a heavyweight operation.
     *
     * @param PBO $db
     * @return boolean true if the delete succeeded.
     */
    public static function deleteProject($db, $projectId) {
        // This will usually fail horribly
        $data = intval(GvProject::$INSTANCE->remove($db, $projectId));
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem removing the project'
                )));
        $deletedCount = $data['rowcount'];
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
        $data = GvBranch::$INSTANCE->create($db, $projectId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating a branch'
                )));
        $id = intval($data['result']);
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
     * @param int $gaUserId
     *            user creating the branch
     * @param int $targetBranchId
     * @param int $parentBranchId
     * @param int $targetChangeId
     * @param int $sourceChangeId
     *            may be null if the head version should be
     *            used.
     * @return int $branchHistoryId
     */
    public static function branchTo($db, $gaUserId, $targetBranchId,
        $sourceBranchId, $targetChangeId, $sourceChangeId = null) {
        if ($sourceChangeId === null) {
            $sourceChangeId = DataAccess::getHeadChange($db, $sourceBranchId);
        }
        
        if (DataAccess::isChangeCommitted($db, $targetChangeId)) {
            throw new Exception("can only branch into a pending change");
        }
        
        // Record the branch in the history
        $data = intval(
            GvBranchHistory::$INSTANCE->create($db, $gaUserId, $targetBranchId,
                $sourceBranchId, $targetChangeId, $sourceVersionId));
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the branch history'
                )));
        $branchHistoryId = intval($data['result']);
        
        //  FIXME ensure the item versions are not already in the branch.
        

        $data = GvChangeVersion::$INSTANCE->runInsertFromChangeItems($db,
            $sourceVersionId, $sourceBranchId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem branching'
                )));
        // TODO Should we check the row counts?
        

        return $branchHistoryId;
    }


    /**
     *
     * @param PBO $db
     * @param int $branchId
     * @return int the head change ID, or null if no change for the branch.
     */
    public static function getHeadChange($db, $branchId) {
        $data = VGvBranchHead::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId,
            // Order largest to smallest
            'Gv_Change_Id DESC');
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem retrieving the version'
                )));
        $ret = $data['result'];
        if (sizeof($ret) <= 0) {
            return null;
        }
        if (sizeof($ret) > 1) {
            error_log(
                "WARNING: Bad view for branch head - it should return " .
                     " at most 1 row per branch, found " . sizeof($ret) . ".");
            // but allow it
        }
        return intval($ret[0]['Gv_Change_Id']);
    }


    /**
     *
     * @param PBO $db
     * @return int the item ID
     */
    public static function createItem($db) {
        $data = GvItem::$INSTANCE->create($db);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating an item'
                )));
        return intval($data['result']);
    }


    /**
     * Creates a new version for the item, and adds it to the change.
     *
     * @param PBO $db
     * @param int $itemId
     * @param int $changeId
     * @param boolean $deleted
     *            true if the item is being deleted, false
     *            (default) otherwise.
     * @return array(int, int) the new item version ([0]), and the change
     *         version
     *         id ([1]).
     */
    public static function addItemToChange($db, $itemId, $changeId,
        $deleted = false) {
        if (DataAccess::isChangeCommitted($db, $changeId)) {
            throw new Exception("can only add items to pending changes");
        }
        
        // ensure the item is not already in the change.
        // NOTE: this is not atomic, so there might be an issue here.  However,
        // changes are intended to be associated with a single user, so this
        // situation is very unlikely.  Famous last words...
        if (DataAccess::getItemVersionFromChange($db, $changeId,
            $itemId) !== false) {
            throw new Base\ValidationException(
                array(
                    "change" => "change already contains this item"
                ));
        }
        
        $alive = (!!$deleted) ? 0 : 1;
        
        $data = GvItemVersion::$INSTANCE->create($db, $itemId, $alive);
        DataAccess::checkError(GvItemVersion::$INSTANCE,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the version'
                )));
        $ivId = intval($data['result']);
        $data = GvChangeVersion::$INSTANCE->create($db, $ivId, $changeId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the version'
                )));
        $cvId = intval($data['result']);
        return array(
            $ivId,
            $cvId
        );
    }


    public static function getItemVersionFromChange($db, $changeId, $itemId) {
        $data = GvItemVersion::$INSTANCE->readBy_Gv_Change_Id_x_Gv_Item_Id($db,
            $changeId, $itemId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem finding the version'
                )));
        if (sizeof($data['result']) <= 0) {
            return false;
        }
        if (sizeof($data['result']) > 1) {
            error_log(
                "WARNING: Found more than 1 version of item " . $itemId .
                     " in change " . $changeId);
            // but keep going...
        }
        return intval($data['result'][0]['Gv_Item_Version_Id']);
    }


    public static function removeItemFromChange($db, $changeId, $itemId) {
        if (DataAccess::isChangeCommitted($db, $changeId)) {
            throw new Exception("can only add items to pending changes");
        }
        
        // Find the version to remove
        $data = GvItemVersion::$INSTANCE->readBy_Gv_Change_Id_x_Gv_Item_Id(
            $db, $changeId, $itemId);
    }


    public static function isChangeCommitted($db, $changeId) {
        $data = GvChange::$INSTANCE->readBy_Gv_Change_Id($db, $changeId);
        DataAccess::checkError(GvChangeVersion::$INSTANCE,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating the version'
                )));
        $rows = $data['result'];
        if (sizeof($rows) <= 0) {
            // might lead to leaking information
            throw new Exception("unknown change id " . $changeId);
        }
        if (sizeof($rows) > 1) {
            // multiple change IDs - should be impossible
            error_log(
                "found multiple committed changes with id " . $changeId);
            // but keep going
        }
        return $rows[0]['Active_State'] == 1 ? true : false;
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
        $data = GvChange::$INSTANCE->create($db, $branchId, 0, $gaUserId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem creating a change'
                )));
        return intval($data['result']);
    }


    public static function countItemsInChange($db, $changeId) {
        $data = GvChangeVersion::$INSTANCE->countBy_Gv_Change_Id($db, $changeId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the change'
                )));
        return intval($data['result']);
    }


    /**
     * Returns all the items in the change.
     *
     * @param PBO $db
     * @param int $changeId
     * @return array() of rows, with each row containing elements
     *         Gv_Change_Version_Id,
     *         Gv_Item_Version_Id, Gv_Change_Id, Created_On, Last_Updated_On
     */
    public static function getItemsInChange($db, $changeId, $rowStart, $rowEnd) {
        $data = GvChangeVersion::$INSTANCE->readBy_Gv_Change_Id($db, $changeId,
            false, $rowStart, $rowEnd);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the change'
                )));
        return $data['result'];
    }


    public static function countPendingChangesForUser($db, $gaUserId, $branchId) {
        $data = VGvPendingChange::$INSTANCE->countBy_Gv_Branch_Id_x_Ga_User_Id(
            $db, $branchId, $gaUserId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        $count = $data['result'];
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
            $db, $branchId, $gaUserId, false, $rowStart, $rowEnd);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        return $data['result'];
    }


    public static function commitChange($db, $changeId) {
        $data = GvChange::$INSTANCE->update($db, $changeId, 1);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        
        if ($data['rowcount'] != 1) {
            throw new Exception(
                "could not find change (" . $data['rowcount'] . ")");
        }
    }


    public static function countChangeHistory($db, $branchId) {
        $data = GvChange::$INSTANCE->countBy_Gv_Branch_Id($db, $branchId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        return $data['result'];
    }


    public static function getChangeHistory($db, $branchId, $start, $end) {
        // order descending
        $data = GvChange::$INSTANCE->readBy_Gv_Branch_Id($db, $branchId,
            "Gv_Change_Id DESC", $start, $end);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        return $data['result'];
    }


    public static function countItemsInChange($db, $changeId) {
        $data = VGvChangeItem::$INSTANCE->countBy_Gv_Change_Id($db, $changeId);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        return $data['result'];
    }


    public static function getItemsInChange($db, $changeId, $start, $end) {
        $data = VGvChangeItem::$INSTANCE->readBy_Gv_Change_Id($db, $changeId,
            false, $start, $end);
        DataAccess::checkError($data,
            new Base\ValidationException(
                array(
                    'unknown' => 'there was an unknown problem searching the changes'
                )));
        return $data['result'];
    }
    

    // ----------------------------------------------------------------------
    private static function checkError($returned, $exception) {
        Base\BaseDataAccess::checkError($returned, $exception);
    }
}


