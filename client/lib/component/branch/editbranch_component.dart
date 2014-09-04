
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
 * The UI component view of the list of films.  The video timer controls are
 * embedded in this component, and are accessed by this component through the
 * `mediaStatusService` field.
 */
@Component(
    selector: 'edit-branch',
    templateUrl: 'packages/webriffs_client/component/branch/editbranch_component.html',
    publishAs: 'cmp')
class EditBranchComponent extends ViewBranchComponent {
    final ServerStatusService _server;
    final UserService _user;

    final QuipDetails pendingQuip = new QuipDetails.pending();


    // FIXME include header editing with the branchinfoedit component.

    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, -1);

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

    void savePendingQuip() {
        // save the quip to the server, add it to our pending quip list,
        // and clear out the pending quip.
    }

    void setPendingQuipTime() {
        if (mediaStatusService.isConnected) {
            pendingQuip.timestamp = mediaStatusService.currentTimeMillis;
        }
    }


    Future<BranchDetails> _loadEditBranchChange(ServerStatusService server,
            int branchId) {
        return server.get('/branch/${branchId}/pending', null)
            .then((ServerResponse response) {
                // FIXME this should return the pending changes between the
                // user's branch-from version and the head version, and it
                // should also include the head revision, so we can pull in the
                // head branch details.
            });
    }
}





