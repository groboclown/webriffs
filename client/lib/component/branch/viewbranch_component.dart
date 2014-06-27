
library viewbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../json/branch_details.dart';
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

    final Future<BranchDetails> branchDetails;
    final int branchId;
    final int changeId;


    factory ViewBranchComponent(ServerStatusService server,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);
        int changeId = int.parse(routeProvider.parameters['changeId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        QuipPaging quips = new QuipPaging(server, branchId, changeId);

        return new ViewBranchComponent._(server, branchId, changeId,
                branchDetails, quips);
    }

    ViewBranchComponent._(this._server, this.branchId, this.changeId,
            this.branchDetails, this.quipPaging);
}

