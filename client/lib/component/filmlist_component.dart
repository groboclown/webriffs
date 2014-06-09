
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

    Future<ServerResponse> _loader;

    bool _expanded = false;

    bool get expanded => _expanded;
    set expanded(bool expand) {
        if (expand && _loader == null) {
            _loader = _createBranchTagLoader();
        }
        _expanded = expand;
    }
    bool get areBranchesLoaded => _branches != null;

    Future<List<BranchRecord>> get branches => _getPendingList(_branches);


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
        this._branches = [];


    Future<ServerResponse> _createBranchTagLoader() {
        return _server.get('/film/${filmId}/branchtags', null)
            .then((ServerResponse resp) {
                // FIXME parse the JSon data
            });
    }


    Future<List<BranchRecord>> _getPendingList(List<BranchRecord> ref) {
        if (_loader == null) {
            _loader = _createBranchTagLoader();
        }
        return _loader.then((_) => ref);
    }
}



class BranchRecord {
    final String name;
    final int id;
    final List<TagRecord> tags;

    BranchRecord._(this.name, this.id, this.tags);

    factory BranchRecord.fromJson(Map<String, dynamic> json) {
        String name = json['name'];
        int id = json['id'];
        List<TagRecord> tags = [];
        json['tags'].forEach((Map<String, dynamic> tagj) {
            tags.add(new TagRecord.fromJson(tagj));
        });
        return new BranchRecord._(name, id, tags);
    }

}


class TagRecord {
    final String name;
    final int id;

    TagRecord._(this.name, this.id);

    factory TagRecord.fromJson(Map<String, dynamic> json) {
        return new TagRecord._(json['name'], json['id']);
    }
}

