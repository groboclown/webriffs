
library viewbranch_component;


import 'package:angular/angular.dart';

import '../../service/server.dart';
import 'quip_paging.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'view-branch',
    templateUrl: 'packages/webriffs_client/component/branch/viewbranch_component.html',
    publishAs: 'cmp')
class ViewBranchComponent {
    final ServerStatusService _server;

    final QuipPaging quipPaging;

    bool get noQuips => quipPaging.quips.length <= 0;

    bool _headerLoaded = false;
    bool get headerLoaded => _headerLoaded;
    int filmId;
    final int branchId;
    final int changeId;


    factory ViewBranchComponent(ServerStatusService server,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);
        int changeId = int.parse(routeProvider.parameters['changeId']);

        // FIXME
        QuipPaging quips = new QuipPaging(server, branchId, changeId);
        return new ViewBranchComponent._(_server, branchId, changeId, quips);
    }

    ViewBranchComponent._(this._server, this.branchId, this.changeId,
            this.quipPaging) {
        _server.get('/branch/${branchId}/version/${changeId}', null)
            .then((ServerResponse response) {

            });


        // FIXME set film ID
        // FIXME load details

        // changeId: if 0, then load the head version from the server.

    }
}

