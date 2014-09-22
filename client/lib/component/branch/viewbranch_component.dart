
library viewbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../json/branch_details.dart';

import 'abstract_branch_component.dart';

/**
 * The UI component view the details of a branch.
 */
@Component(
    selector: 'view-branch',
    templateUrl: 'packages/webriffs_client/component/branch/viewbranch_component.html',
    publishAs: 'cmp')
class ViewBranchComponent extends AbstractBranchComponent {

    factory ViewBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);
        int changeId = int.parse(routeProvider.parameters['changeId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        return new ViewBranchComponent.direct(server, user, branchId, changeId,
                branchDetails);
    }

    ViewBranchComponent.direct(ServerStatusService server, UserService user,
            int branchId, int urlChangeId,
            Future<BranchDetails> branchDetails) :
            super(server, user, branchId, urlChangeId, branchDetails);
}

