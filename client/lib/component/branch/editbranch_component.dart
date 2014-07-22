
library editbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';

import 'quip_paging.dart';
import 'viewbranch_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'edit-branch',
    templateUrl: 'packages/webriffs_client/component/branch/editbranch_component.html',
    publishAs: 'cmp')
class EditBranchComponent extends ViewBranchComponent {
    final ServerStatusService _server;
    final UserService _user;

    final QuipDetails pendingQuip = new QuipDetails.pending();

    int currentMillisTime;

    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);
        int changeId = int.parse(routeProvider.parameters['changeId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        QuipPaging quips = new QuipPaging(server, branchId, changeId);

        return new EditBranchComponent._(server, user, branchId, changeId,
                branchDetails, quips);
    }

    EditBranchComponent._(ServerStatusService server, UserService user,
            int branchId, int urlChangeId, Future<BranchDetails> branchDetails,
            QuipPaging quipPaging) :
            _server = server,
            _user = user,
            super.direct(server, user, branchId, urlChangeId, branchDetails,
                    quipPaging);


    void startTimer() {
        // FIXME
    }

    void stopTimer() {
        // FIXME
    }

    void savePendingQuip() {
        // save the quip to the server, add it to our pending quip list,
        // and clear out the pending quip.
    }
}

