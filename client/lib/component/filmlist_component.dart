
library filmlist_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../service/server.dart';

import '../util/paging.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'film-list',
    templateUrl: 'packages/webriffs_client/component/filmlist_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class FilmListComponent {
    final ServerStatusService _server;

    PageState pageState;

    final List<FilmRecord> films = [];

    bool get noFilms => films.length <= 0;

    FilmListComponent(this._server) {
        pageState = new PageState(this._server, '/film',
            (PageState ps, Iterable<dynamic> fl) {
                films.clear();
                fl.forEach((Map<String, dynamic> json) {
                    films.add(new FilmRecord.fromJson(_server, json));
                });
            });
        pageState.updateFromServer();
    }
}




class FilmRecord {
    final ServerStatusService _server;

    final String name;
    final int releaseYear;
    final int filmId;
    final List<BranchRecord> _branches;
    Future<List<BranchRecord>> _pendingBranches;
    Completer<int> _pendingBranchCount;
    bool _loading = true;

    bool get loading => _loading;

    bool _expanded = false;

    bool get expanded => _expanded;
    set expanded(bool expand) {
        if (expand && _pendingBranches == null) {
            _branchTagLoader();
        }
        _expanded = expand;
    }
    bool get areBranchesLoaded => _branches != null;

    Future<List<BranchRecord>> get branches => _pendingBranches;
    Future<int> get branchCount => _pendingBranchCount.future;


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


    FilmRecord._(this._server, this.filmId, int projectId, this.name,
            this.releaseYear,
            dynamic createdOn, dynamic lastUpdatedOn) :
        this._branches = [],
        this._pendingBranchCount = new Completer<int>();


    void toggleExpanded() {
        expanded = ! expanded;
    }



    void reset() {
        _expanded = false;
        _branches.clear();
        _pendingBranchCount = new Completer<int>();
        _pendingBranches = null;
    }

    Future<List<BranchRecord>> _branchTagLoader() {
        if (_pendingBranches == null) {
            // Only load the top 10 tags
            PageState pageState = new PageState(_server, '/film/${filmId}/branch',
                    (PageState pageState, Iterable<dynamic> data) {
                        data.forEach((Map<String, dynamic> row) {
                            _branches.add(new BranchRecord.fromJson(
                                    _server, filmId, row));
                        });
                        _pendingBranchCount.complete(pageState.recordCount);
                        _loading = false;
                    });
            _pendingBranches = pageState.updateFromServer(
                    newRecordsPerPage: 10).then((_) => _branches);
        }
        return _pendingBranches;
    }
}



class BranchRecord {
    final String name;
    final int filmId;
    final int branchId;
    final createdOn;
    final lastUpdatedOn;
    final List<TagRecord> _tags;
    bool _loading = true;

    bool get loading => _loading;

    Future<List<TagRecord>> _pendingTags;

    Future<List<TagRecord>> get tags => _pendingTags;



    BranchRecord._(ServerStatusService server, this.name, this.filmId,
            this.branchId, this.createdOn,
            this.lastUpdatedOn) : _tags = [] {
        _pendingTags = server.get('/film/${filmId}/branch/${branchId}/tag',
            null).then((ServerResponse result) {
                result.jsonData['tags'].foreach((Map<String, dynamic> tag) {
                    _tags.add(new TagRecord.fromJson(tag));
                });
                _loading = false;
                return _tags;
            });
    }

    factory BranchRecord.fromJson(ServerStatusService server, int filmId,
            Map<String, dynamic> json) {
        String name = json['Branch_Name'];
        int id = json['Film_Branch_Id'];
        var created = json['Branch_Created_On'];
        var updated = json['Branch_Last_Updated_On'];
        return new BranchRecord._(server, name, filmId, id, created, updated);
    }

}



class TagRecord {
    final String name;

    TagRecord._(this.name);

    factory TagRecord.fromJson(Map<String, dynamic> json) {
        return new TagRecord._(json['Tag_Name']);
    }
}

