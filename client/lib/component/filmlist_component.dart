
library filmlist_component;


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
                    films.add(new FilmRecord.fromJson(json));
                });
            });
        pageState.updateFromServer();
    }
}




class FilmRecord {
    String name;
    int releaseYear;
    List<String> branches;
    List<String> tags;

    factory FilmRecord.fromJson(Map<String, dynamic> json) {
        // FIXME
        int filmId = json['Film_Id'];
        int projectId = json['Gv_Project_Id'];
        String name = json['Name'];
        int releaseYear = json['Release_Year'];
        dynamic createdOn = json['Created_On']; // datetime -> ?
        dynamic lastUpdatedOn = json['Last_Updated_On']; // datetime -> ?

        return new FilmRecord._(filmId, projectId, name, releaseYear,
                createdOn, lastUpdatedOn);
    }


    FilmRecord._(int filmId, int projectId, String name, int releaseYear,
            dynamic createdOn, dynamic lastUpdatedOn) {
        this.name = name;
        this.releaseYear = releaseYear;
        this.branches = [];
        this.tags = [];
    }
}
