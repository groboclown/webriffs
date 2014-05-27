
library createfilm_component;


import 'package:angular/angular.dart';

import '../service/server.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'create-film',
    templateUrl: 'packages/webriffs_client/component/createfilm_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class CreateFilmComponent {
    ServerStatusService _error;

    CreateFilmComponent(this._error);
}
