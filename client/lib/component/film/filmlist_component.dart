
library filmlist_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';

import '../../json/branch_details.dart';

import '../../util/async_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'film-list',
    templateUrl: 'packages/webriffs_client/component/film/filmlist_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class FilmListComponent extends PagingComponent {
    final ServerStatusService _server;

    final List<FilmRecord> films = [];

    bool get noFilms => films.length <= 0;

    FilmListComponent(ServerStatusService server) :
            _server = server,
            super(server, '/film') {
        update();
    }


    Future<ServerResponse> onSuccess(Iterable<dynamic> data) {
        films.clear();
        data.forEach((Map<String, dynamic> json) {
            films.add(new FilmRecord.fromJson(_server, json));
        });
        return null;
    }

}



// TODO make the list of top branches returned be the "most popular" branches,
// sub-sorted by the last update time.
// FIXME move this into the "lib/json/film_details.dart" file.
class FilmRecord extends PagingComponent {
    final ServerStatusService _server;

    final String name;
    final int releaseYear;
    final int filmId;

    final List<BranchRecord> branches = [];
    int get branchCount => current.recordCount;
    int get maxShown => current.recordsPerPage;
    int get remainingBranches => branchCount - maxShown;

    bool _expanded = false;
    bool get expanded => _expanded;

    set expanded(bool expand) {
        if (expand && ! loadedSuccessful) {
            update();
        }
        _expanded = expand;
    }


    factory FilmRecord.fromJson(ServerStatusService server,
            Map<String, dynamic> json) {
        int filmId = json['Film_Id'];
        int projectId = json['Gv_Project_Id'];
        String name = json['Name'];
        int releaseYear = json['Release_Year'];
        dynamic createdOn = json['Created_On']; // datetime -> ?
        dynamic lastUpdatedOn = json['Last_Updated_On']; // datetime -> ?

        return new FilmRecord._(server, filmId, projectId, name, releaseYear,
                createdOn, lastUpdatedOn);
    }


    FilmRecord._(ServerStatusService server, int filmId, int projectId,
            this.name, this.releaseYear,
            dynamic createdOn, dynamic lastUpdatedOn) :
        _server = server,
        filmId = filmId,
        super(server, '/film/' + filmId.toString() + '/branch');


    void toggleExpanded() {
        expanded = ! expanded;
    }



    void reset() {
        _expanded = false;
        branches.clear();
    }

    Future<ServerResponse> onSuccess(Iterable<dynamic> data) {
        branches.clear();
        data.forEach((Map<String, dynamic> row) {

            // FIXME once film/.../branch returns in the list the same data
            // as /branch/.../version/..., switch this to use BranchDetails.

            branches.add(new BranchRecord.fromJson(_server, filmId, row));
        });
        return null;
    }
}


// FIXME move this into the "lib/json/branch_details.dart" file.
class BranchRecord {
    final ServerStatusService _server;
    final String name;
    final String description;
    final int filmId;
    final int branchId;
    final int headChangeId;
    final createdOn;
    final lastUpdatedOn;
    final List<TagRecord> tags;

    BranchRecord._(this._server, this.name, this.description, this.filmId,
            this.branchId, this.headChangeId, this.createdOn,
            this.lastUpdatedOn, this.tags);

    factory BranchRecord.fromJson(ServerStatusService server, int filmId,
            Map<String, dynamic> json) {
        String name = json['Branch_Name'];
        String desc = json['Description'];
        int id = json['Gv_Branch_Id'];
        int changeId = json['Gv_Change_Id'];
        var created = json['Branch_Created_On'];
        var updated = json['Branch_Last_Updated_On'];
        List<TagRecord> tags = [];
        for (Map<String, dynamic> tag in json['tags']) {
            tags.add(new TagRecord.fromJson(tag));
        }
        if (filmId.toString() != json['Film_Id'].toString()) {
            print("Branch " + name + ": Film IDs don't match (" +
                    filmId.toString() + " vs " + json['Film_Id'].toString() + ")");
        }
        return new BranchRecord._(server, name, desc, filmId, id, changeId,
                created, updated, tags);
    }
}



class TagRecord {
    final String name;

    TagRecord._(this.name);

    factory TagRecord.fromJson(dynamic json) {
        // For the moment, a Tag is just a string.
        if (! (json is String)) {
            throw new Exception("tag data is not a string: ${json}");
        }
        return new TagRecord._(json);
    }
}

