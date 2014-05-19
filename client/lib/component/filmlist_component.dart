
library filmlist_component;


import 'package:angular/angular.dart';

import '../service/serverstatus.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'film-list',
    templateUrl: 'packages/webriffs_client/component/filmlist_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class FilmListComponent {
    ServerStatusService _error;

    FilmListComponent(this._error);
}
