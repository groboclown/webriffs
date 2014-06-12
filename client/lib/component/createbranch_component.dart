
library createbranch_component;


import 'package:angular/angular.dart';

import '../service/server.dart';

import '../util/paging.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'view-branch',
    templateUrl: 'packages/webriffs_client/component/createbranch_component.html',
    publishAs: 'cmp')
class CreateBranchComponent {
    final ServerStatusService _server;

    int filmId;

    CreateBranchComponent(this._server, RouteProvider routeProvider) {
        filmId = int.parse(routeProvider.parameters['filmId']);
    }
}

