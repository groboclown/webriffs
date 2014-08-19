
library branch_details;

import 'dart:async';

import '../service/server.dart';

Future<BranchDetails> loadBranchDetails(ServerStatusService server,
        int branchId, int changeId) {
    return server.get('/branch/${branchId}/version/${changeId}', null)
        .then((ServerResponse response) {
            if (response.wasError || response.jsonData == null) {
                return null;
            }
            return new BranchDetails.fromJson(response.jsonData);
        });
}



class BranchDetails {
    int filmId;
    int filmReleaseYear;
    String filmName;
    String filmCreatedOn;
    String filmLastUpdatedOn;

    final int branchId;
    int changeId;
    String name;
    String description;
    String updatedOn;

    List<BranchTagDetails> tags;



    factory BranchDetails.fromJson(Map<String, dynamic> json) {
        var ret = new BranchDetails._(json['Gv_Branch_Id']);
        ret._loadJson(json, true);
        return ret;
    }


    BranchDetails._(this.branchId);


    void update(Map<String, dynamic> json) {
        _loadJson(json, false);
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
    }
}


/**
 * A tag for a branch.  For now, this is just a string.
 */
class BranchTagDetails {
    String name;

    factory BranchTagDetails.fromJson(dynamic json) {
        if (json != null && json is String) {
            return new BranchTagDetails(name);
        }
        throw new Exception("invalid json data: expected string for tag");
    }


    BranchTagDetails(this.name);
}

