
library editbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/event_util.dart';

import '../media/media_status.dart';

import 'quip_paging.dart';
import 'abstract_branch_component.dart';


/**
 * The UI component view of the list of films.  The video timer controls are
 * embedded in this component, and are accessed by this component through the
 * `mediaStatusService` field.
 *
 * FIXME add "is editing" mode for quips.  This is turned on and off only by
 *      the presence of a pending change on the branch.  This will mean an
 *      extension to the branch details to get that additional info, maybe?
 *      It should be in a request that returns BEFORE the branch details
 *      future returns.  Changing this state may have large implications on
 *      the displayed UI.
 *
 * FIXME should not have the change ID as part of the UI.  Instead, it
 * should be just a notion of determining what has changed based on what
 * the user is currently viewing.  The "branch updates" component needs to
 * be better integrated into this, so that it detects what quips were added,
 * and by whom, and then pushes those down to consumers (Stream events).
 * When a user is editing, they are in a "pending merge" state, to allow for
 * an easier merge click-through.
 *
 *
 */
@Component(
    selector: 'edit-branch',
    templateUrl: 'packages/webriffs_client/component/branch/editbranch_component.html',
    publishAs: 'cmp')
class EditBranchComponent extends AbstractBranchComponent {
    static final Logger _log = new Logger('media.EditBranchComponent');

    final ServerStatusService _server;
    final UserService _user;

    /**
     * Communication layer between the real service, when it finally is
     * initialized, and whatever it may be, and this outer component.
     */
    final MediaStatusServiceConnector mediaStatusService;

    final QuipPaging quipPaging;

    bool get noQuips => quipPaging.quips.length <= 0;


    final StreamController<QuipDetails> _changePendingQuipEvents;
    final StreamProvider<QuipDetails> quipChangedEvents;


    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        List<int> ids = AbstractBranchComponent.
                parseRouteParameters(routeProvider);
        int branchId = ids[0];
        int changeId = ids[1];

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, -1);

        QuipPaging quips = new QuipPaging.pending(server, branchId);

        StreamController<QuipDetails> quipEvents =
                new StreamController<QuipDetails>();

        return new EditBranchComponent._(server, user, branchId, changeId,
                branchDetails, quips, quipEvents);
    }

    EditBranchComponent._(ServerStatusService server, UserService user,
            int branchId, int changeId, Future<BranchDetails> branchDetails,
            this.quipPaging, StreamController<QuipDetails> quipEvents) :
            _server = server,
            _user = user,
            _changePendingQuipEvents = quipEvents,
            quipChangedEvents =
                new StreamControllerStreamProvider<QuipDetails>(quipEvents),
            mediaStatusService = new MediaStatusServiceConnector(),
            super(server, user, branchId, changeId, branchDetails);


    /**
     * Load all the changes that have happened
     */
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

