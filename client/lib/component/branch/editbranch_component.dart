
library editbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/event_util.dart';

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
    static final Logger _log = new Logger('media.EditBranchComponent');

    final ServerStatusService _server;
    final UserService _user;

    final StreamController<QuipDetails> _changePendingQuipEvents;
    final StreamProvider<QuipDetails> quipChangedEvents;


    // FIXME include header editing with the branchinfoedit component.

    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, -1);

        QuipPaging quips = new QuipPaging.pending(server, branchId);

        StreamController<QuipDetails> quipEvents =
                new StreamController<QuipDetails>();

        return new EditBranchComponent._(server, user, branchId,
                branchDetails, quips, quipEvents);
    }

    EditBranchComponent._(ServerStatusService server, UserService user,
            int branchId, Future<BranchDetails> branchDetails,
            QuipPaging quipPaging, StreamController<QuipDetails> quipEvents) :
            _server = server,
            _user = user,
            _changePendingQuipEvents = quipEvents,
            quipChangedEvents =
                new StreamControllerStreamProvider<QuipDetails>(quipEvents),
            super.direct(server, user, branchId, null, branchDetails,
                    quipPaging);


    Future<BranchDetails> _loadEditBranchChange(ServerStatusService server) {
        return branchDetails
            .then((BranchDetails details) =>
                    details.updateFromServer(server))
            .then((ServerResponse response) =>
                    server.get('/branch/${branchId}/pending', null))
            .then((ServerResponse response) {
                // FIXME this should return the pending changes between the
                // user's branch-from version and the head version, and it
                // should also include the head revision, so we can pull in the
                // head branch details.

                return branchDetails;
            });
    }
}

