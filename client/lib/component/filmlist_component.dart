
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

    FilmListComponent(this._server) {
        pageState = new PageState(this._server, '/film',
            (PageState ps, Iterable<dynamic> fl) {
                films.clear();
                fl.forEach((Map<String, dynamic> json) {
                    films.add(new FilmRecord.fromJson(json));
                });
            });
    }
}




class FilmRecord {
    String name;
    int releaseYear;
    List<String> branches;
    List<String> tags;

    factory FilmRecord.fromJson(Map<String, dynamic> json) {
        // FIXME

        return new FilmRecord._();
    }


    FilmRecord._() {
        // FIXME
    }
}
