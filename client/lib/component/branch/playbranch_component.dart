
library playbranch_component;


import 'package:angular/angular.dart';

import '../../service/server.dart';

import '../../util/paging.dart';

/**
 * The UI component to playback quips in a branch.
 */
@Component(
    selector: 'play-branch',
    templateUrl: 'packages/webriffs_client/component/branch/playbranch_component.html',
    publishAs: 'cmp')
class PlayBranchComponent {
    final ServerStatusService _server;

    int filmId;
    int branchId;
    int changeId;

    PlayBranchComponent(this._server, RouteProvider routeProvider) {
        branchId = int.parse(routeProvider.parameters['branchId']);
        changeId = int.parse(routeProvider.parameters['changeId']);
    }
}

