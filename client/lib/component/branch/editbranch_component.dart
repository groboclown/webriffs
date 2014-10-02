
library editbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/event_util.dart';

import '../media/alert_controller.dart';

import 'quip_paging.dart';
import 'abstract_branch_component.dart';


/**
 * The UI component view of the list of films.  The video timer controls are
 * embedded in this component, and are accessed by this component through the
 * `mediaStatusService` field.
 *
 * FIXME add "is editing" mode for quips.  This is turned on and off only by
 *  the presence of a pending change on the branch.  This will mean an
 *  extension to the branch details to get that additional info, maybe?
 *  It should be in a request that returns BEFORE the branch details
 *  future returns.  Changing this state may have large implications on
 *  the displayed UI.
 *
 * FIXME should not have the change ID as part of the UI.  Instead, it
 * should be just a notion of determining what has changed based on what
 * the user is currently viewing.  The "branch updates" component needs to
 * be better integrated into this, so that it detects what quips were added,
 * and by whom, and then pushes those down to consumers (Stream events).
 * When a user is editing, they are in a "pending merge" state, to allow for
 * an easier merge click-through.
 *
 * The change ID can be part of the UI if the user wants to look at historical
 * versions of the branch.
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
    final int requestedChangeId;

    final QuipPaging quipPaging;

    QuipMediaAlertController get mediaAlertController =>
            quipPaging.mediaAlertController;

    bool get noQuips => quipPaging.quips.isEmpty;

    // If the user can't edit the quips, or the user requested to see an old
    // version, then don't allow edits.
    bool get isEditable => canEditQuips && requestedChangeId < 0;


    final VideoPlayerTimeProvider videoTimeProvider =
            new VideoPlayerTimeProvider();


    final StreamController<QuipDetails> _requestEditQuipEvents;
    final StreamProvider<QuipDetails> requestEditEvents;


    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        List<int> ids = AbstractBranchComponent.
                parseRouteParameters(routeProvider);
        int branchId = ids[0];
        int changeId = ids[1];

        if (changeId == null) {
            // User didn't specify a change id, so use the "get the current"
            // as the change id.
            changeId = -1;
        }

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        QuipPaging quips = new QuipPaging(server, branchId, changeId);

        StreamController<QuipDetails> quipEvents =
                new StreamController<QuipDetails>.broadcast();

        return new EditBranchComponent._(server, user, branchId, changeId,
                branchDetails, quips, quipEvents, changeId);
    }

    EditBranchComponent._(ServerStatusService server, UserService user,
            int branchId, int changeId, Future<BranchDetails> branchDetails,
            this.quipPaging, StreamController<QuipDetails> quipEvents,
            this.requestedChangeId) :
            _server = server,
            _user = user,
            _requestEditQuipEvents = quipEvents,
            requestEditEvents =
                new StreamControllerStreamProvider<QuipDetails>(quipEvents),
            super(server, user, branchId, changeId, branchDetails);


    void editQuip(QuipDetails quip) {
        _requestEditQuipEvents.add(quip);
    }


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

