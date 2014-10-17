<?php

namespace WebRiffsRest;

require_once(__DIR__.'/Resource.php');
require_once(__DIR__.'/Film.php');

use Tonic;
use WebRiffs;
use Base;


/**
 * Retrieves the head version number for the branch.  Used for standard
 * viewing of a branch, so that subsequent submits won't affect the
 * current viewing.
 *
 * @uri /branch/:branchid/head
 */
class BranchObjHeadVersion extends Resource {
    /**
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $result = WebRiffs\BranchLayer::getHeadBranchVersion($this->getDB(),
            $userId, $branchId);
        
        return array(200, $result);
    }
}


/**
 * All committed changes for the branch.
 *
 * @uri /branch/:branchid/version
 */
class BranchObjChanges extends Resource {
    /**
     * Retrieve all the changes for the branch.  This is a paging operation.
     *
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $result = WebRiffs\BranchLayer::pageBranchVersions($this->getDB(),
            $userId, $branchId, null);
        
        return array(200, $result);
    }
    
    
    /**
     * Edit the branch header.
     *
     * @method POST
     * @csrf edit_branch
     */
    function update() {
        $branchId = $this->validateId($this->branchid, "branchId");
        
        $branchName = Validation::normalizeBranchName(
                $this->loadRequestString('Branch_Name'), $this);
        $desc = $this->loadRequestString('Description', FALSE) or "";
        
        $this->validate();
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        $tags = array();
        $data = $this->getRequestData();
        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                if (!! $tag && is_string($tag)) {
                    $tag = trim($tag);
                    if (strlen($tag) > 0) {
                        $tags[] = $tag;
                    }
                }
            }
        }
        
        $this->validate();
        
        // DEBUG
        //error_log("Film.php: description: [".$desc."]");
        WebRiffs\BranchLayer::updateBranchHeader($this->getDB(), $branchId,
            $userId, $gaUserId, $branchName, $desc, $tags);
        
        return new Tonic\Response(Tonic\Response::ACCEPTED);
    }
    
    
    /**
     * Calls the post method.  Conceptually, the user is updating a branch,
     * but it can also be viewed as adding a new change to the branch.  So
     * we'll allow both.
     *
     * @method PUT
     * @csrf edit_branch
     */
    function create() {
        return $this->update();
    }
}


/**
 * Returns details about committed changes on a branch.
 *
 * @uri /branch/:branchid/version/:changeid
 */
class BranchObjChangeVersion extends Resource {
    
    /**
     * Get the details for the branch.  If the branch is not visible by the
     * user, then it returns a "no content" response.
     *
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeId = $this->validateId($this->changeid, "changeId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        $ret = WebRiffs\BranchLayer::getBranchDetails($this->getDB(),
            $userId, $branchId, $changeId);
        
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
    }
}



/**
 * Paging for the committed branch quips.
 *
 * @uri /branch/:branchid/version/:changeid/quip
 */
class BranchObjQuips extends Resource {
    
    /**
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeId = $this->validateId($this->changeid, "changeId");
        $this->validate();
    
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        $ret = WebRiffs\QuipLayer::pageCommittedQuips($this->getDB(),
                $userId, $branchId, $changeId);
    
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
    }
}


/**
 * Lists all of the branches (and their films) in which the user has a pending
 * change.
 *
 * @uri /branch/pending
 */
class BranchObjUserPendingBranches extends Resource {
    
    /**
     * @method GET
     * @authenticated
     */
    function pageBranches() {
        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        // FIXME
        return array(500);
    }
}


/**
 * Returns the user's version of the branch on which it was created.  It
 * is also used to merge the pending change, delete, create, and commit.
 *
 * @uri /branch/:branchid/pending
 */
class BranchObjUserPendingVersion extends Resource {
    /**
     * Returns the branch version that
     * the user's pending was branched against, and the (head - N, up to
     * the pending change number) number of changes that have happened on the
     * branch since the user branch.  It also returns the number of branch
     * changes that have happened since the pending change was created.
     *
     * If the user does not have an open change for the branch, then this
     * will return no content.
     *
     * @method GET
     * @authenticated
     */
    function fetchBranchVersion() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeCount = $this->loadGetInt("changes", FALSE) || 0;
        $this->validate();
        
        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        
        // FIXME call into getBranchQuipChangesFromPending
        return array(500);
    }
    
    
    /**
     * @method PUT
     * @csrf create_change
     */
    function createPendingChange() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();

        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        $changeId = $this->loadRequestInt("changes", FALSE) || -1;
        if ($changeId < 0) {
            $changeId = WebRiffs\BranchLayer::getHeadBranchVersion(
                    $this->getDB(), $userId, $branchId);
        }
        
        // This will either create the change, or update an existing change
        // with the new change id
        WebRiffs\QuipLayer::createPendingChange($this->getDB(), $userId,
            $gaUserId, $branchId, $changeId);
        
        // created
        return array(201, array());
    }
    
    
    /**
     * @method DELETE
     * @csrf delete_change
     */
    function deletePendingChange() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $action = $this->loadRequestString("action");
        $this->validate();

        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        WebRiffs\QuipLayer::deletePendingChange($this->getDB(),
                    $userId, $gaUserId, $branchId);
        
        return array(202, array());
    }
    
    
    /**
     * @method POST
     * @csrf commit_change
     */
    function commitChange() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $action = $this->loadRequestString("action");
        $this->checkThat($action != "commit" && $action != "merge",
            "action");
        $this->validate();

        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        if ($action == "commit") {
            $change = WebRiffs\QuipLayer::commitPendingChange($this->getDB(),
                    $userId, $gaUserId, $branchId);
            return array(201, array("Committed_Change_Id" => $change));
        } else {
            // FIXME Add MERGE support.
            // MERGE will move the user's branched-from change to the current
            // head
            return array(500, array("message" => "unsupported action"));
        }
    }
}


/**
 * Paging for the pending branch quips of the current user, and adding new
 * quips.
 *
 * @uri /branch/:branchid/pending/quip
 */
class BranchObjQuipsPending extends Resource {
    /**
     * Page in the pending and committed quips.  Each returned quip includes
     * a field to indicate whether it is pending or committed.  The whole
     * returned structure includes the version number requested.
     *
     * If the request is not authenticated, or if the user does not have an
     * open change, then this will return an error message.  This is because
     * there is no specific change number in the request, so the client side
     * can potentially get into a bad state during playback if this returned
     * the head revision.
     *
     * @method GET
     * @authenticated
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = $this->container['user']['User_Id'];
        
        $ret = WebRiffs\QuipLayer::pageCommittedPendingQuips($this->getDB(),
                $userId, $branchId);
        
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
    }
    
    
    /**
     * Add a (1) new quip.
     *
     * @method PUT
     * @csrf create_quip
     */
    function create() {
        $branchId = $this->validateId($this->branchid, "branchId");
        
        $quipText = $this->loadRequestString("Text_Value");
        $quipTime = $this->loadRequestInt("Timestamp_Millis");
        $this->validate();
        
        
        // FIXME add quip tags
        $quipTags = array();
        
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        
        $ret = WebRiffs\QuipLayer::saveQuip($this->getDB(),
            $userId, $gaUserId, $branchId, null, $quipText, $quipTime,
            $quipTags);
        
        return array(201, $ret);
    }
    
    
    /**
     * Performs a series of actions over many quips.  The JSon object is broken
     * down into different actions by way of the top-level key.  This allows
     * multiple simulated requests to the /branch/:branchid/pending/quip/:itemid
     * URL, without the need for lots of independent connections.
     *
     * If there are issues with the request, they are returned in the JSon
     * response (just like all errors), with one per request object, in a
     * similar structure to how it was requested.
     *
     * TODO should be investigated into put a maximum size to the request,
     * to eliminate a possible hog of resources in a single request.
     *
     * @method POST
     * @csrf mass_update_quip
     */
    function mass_updates() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
                
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = $this->getRequestData();
        if (array_key_exists("create", $data) && is_array($data['create'])) {
            // FIXME
        }
        if (array_key_exists('delete', $data) && is_array($data['delete'])) {
            // FIXME
        }
        if (array_key_exists('update', $data) && is_array($data['update'])) {
            // FIXME
        }
        
        return array(500);
    }
}


/**
 *
 * @uri /branch/:branchid/pending/quip/:itemid
 */
class BranchObjQuipItem extends Resource {
    /**
     * Retrieve the detailed information for this one specific quip.  If the
     * quip is not in the pending change for the user, then nothing is
     * returned (no content).
     *
     * @method GET
     */
    function fetch() {
        // FIXME
        return array(500);
    }
    
    
    /**
     * Update the information on this pending quip.  If this is a quip in
     * the branch but not in the pending list, then it should be added to
     * the pending list as an update to the quip.
     *
     * This can potentially update the quip tags, so the tag access needs to
     * be checked separately.
     *
     * @method POST
     * @csrf update_quip
     */
    function update() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $quipId = $this->validateId($this->itemid, "itemId");
        $this->validate();
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        // FIXME
        return array(500);
    }
    
    
    /**
     * Remove the quip.
     *
     * @method DELETE
     * @csrf delete_quip
     */
    function remove() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $quipId = $this->validateId($this->itemid, "itemId");
        $this->validate();
                
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        // FIXME
        return array(500);
    }
}

