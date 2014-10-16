
library branch_details;

import 'dart:async';

import '../service/server.dart';

import 'film_details.dart';

/**
 * Load all the details for the branch and change.  If you want the head
 * revision, use a changeId = -1.
 */
Future<BranchDetails> loadBranchDetails(ServerStatusService server,
        int branchId, int changeId) {
    return server.get('/branch/${branchId}/version/${changeId}', null)
        .then((ServerResponse response) {
            if (response.wasError) {
                return null;
            }
            if (response.jsonData == null) {
                return null;
            }
            return new BranchDetails.fromJson(response.jsonData, changeId < 0);
        });
}


class BranchDetails {
    int filmId;
    int filmReleaseYear;
    String filmName;
    String filmCreatedOn;
    String filmLastUpdatedOn;

    final int branchId;
    final bool requestingHeadRevision;
    int changeId;
    String name;
    String description;
    String updatedOn;

    List<BranchTagDetails> tags;

    bool userCanEditBranch = false;
    bool userCanDeleteBranch = false;
    bool userCanEditBranchTags = false;
    bool userCanEditBranchPermissions = false;

    bool userCanReadQuips = false;
    bool userCanEditQuips = false;
    bool userCanEditQuipTags = false;

    List<LinkRecord> filmLinks = [];

    bool _loading = false;
    bool get loading => _loading;

    int userPendingChangeId;
    int userBaseChangeId;
    bool userHasPendingChange = false;



    factory BranchDetails.fromJson(Map<String, dynamic> json, bool isHead) {
        var ret = new BranchDetails._(json['Gv_Branch_Id'], isHead);
        ret._loadJson(json, true);
        return ret;
    }


    BranchDetails._(this.branchId, this.requestingHeadRevision);


    void update(Map<String, dynamic> json) {
        _loadJson(json, false);
    }


    /**
     * Returns the JSON related to the branch header values (for saving).
     */
    Map<String, dynamic> toSaveJson() {
        var ret = <String, dynamic> {
            'Branch_Name': name,
            'Description': description,
            'tags': new List.from(
                tags.map(
                    (BranchTagDetails btd) => btd.toSaveJson()))
        };

        return ret;
    }


    /**
     * Load the details from the server.  This will return as a future with
     * the server response, so errors can be handled appropriately.  On an
     * error, this object will not be updated.
     */
    Future<ServerResponse> updateFromServer(ServerStatusService server) {
        _loading = true;
        int reqChange = changeId;
        if (requestingHeadRevision) {
            reqChange = -1;
        }
        return server.get('/branch/${branchId}/version/${reqChange}', null)
            .then((ServerResponse response) {
                if (! response.wasError && response.jsonData != null) {
                    update(response.jsonData);
                }
                return response;
            }).whenComplete(() {
                _loading = false;
            });
    }


    void _loadJson(Map<String, dynamic> json, bool initialLoad) {
        int fid = json['Film_Id'];
        String fn = json['Film_Name'];
        int fry = json['Release_Year'];
        String fco = json['Film_Created_On'];
        String fluo = json['Film_Last_Updated_On'];
        int gbi = json['Gv_Branch_Id'];
        int cid = json['Gv_Change_Id'];
        String bn = json['Branch_Name'];
        String d = json['Description'];
        String uo = json['Updated_On'];
        List<BranchTagDetails> btd = [];
        if (json.containsKey('tags') && json['tags'] is List) {
            json['tags'].forEach((var j) {
                btd.add(new BranchTagDetails.fromJson(j));
            });
        }
        if (gbi != branchId) {
            throw new Exception("invalid state: branch id changed");
        }
        if (initialLoad) {
            filmId = fid;
        } else {
            if (fid != filmId) {
                throw new Exception("invalid state: film id changed (was " +
                        fid.toString() + ", but now is " + filmId.toString() + ")");
            }
            // the rest of the film information can change, because someone
            // else can edit it underneath you.
        }
        filmName = fn;
        filmReleaseYear = fry;
        filmCreatedOn = fco;
        filmLastUpdatedOn = fluo;
        changeId = cid;
        name = bn;
        description = d;
        updatedOn = uo;
        tags = btd;

        userCanEditBranch = false;
        userCanDeleteBranch = false;
        userCanEditBranchTags = false;
        userCanEditBranchPermissions = false;
        userCanReadQuips = false;
        userCanEditQuips = false;
        userCanEditQuipTags = false;
        if (json.containsKey('access') && json['access'] is Map) {
            Map<String, dynamic> access = json['access'];
            if (access.containsKey('branch-write') && access['branch-write']) {
                userCanEditBranch = true;
            }
            if (access.containsKey('branch-del') && access['branch-del']) {
                userCanDeleteBranch = true;
            }
            if (access.containsKey('branch-tag') && access['branch-tag']) {
                userCanEditBranchTags = true;
            }
            if (access.containsKey('branch-users') && access['branch-users']) {
                userCanEditBranchPermissions = true;
            }

            if (access.containsKey('quip-read') && access['quip-read']) {
                userCanReadQuips = true;
            }
            if (access.containsKey('quip-write') && access['quip-write']) {
                userCanEditQuips = true;
            }
            if (access.containsKey('quip-tag') && access['quip-tag']) {
                userCanEditQuipTags = true;
            }
        }

        if (json.containsKey('film_links') && json['film_links'] is List) {
            filmLinks = [];
            for (var link in json['film_links']) {
                if (link is Map) {
                    filmLinks.add(new LinkRecord.fromJson(link));
                }
            }
        }

        userPendingChangeId = -1;
        userBaseChangeId = -1;
        userHasPendingChange = false;
        if (json.containsKey('pending') && json['pending'] != null &&
                json['pending'] is Map) {
            userPendingChangeId = json['pending']['Pending_Change_Id'];
            userBaseChangeId = json['pending']['Base_Change_Id'];
            userHasPendingChange = true;
        }
    }
}


/**
 * A tag for a branch.  For now, this is just a string.
 */
class BranchTagDetails {
    String name;

    factory BranchTagDetails.fromJson(dynamic json) {
        if (json != null && json is String) {
            return new BranchTagDetails(json);
        }
        throw new Exception("invalid json data: expected string for tag");
    }


    BranchTagDetails(this.name);

    dynamic toSaveJson() {
        return name;
    }
}
