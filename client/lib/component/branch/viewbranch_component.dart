
library viewbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../json/branch_details.dart';

import '../media/media_status.dart';

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
    final UserService _user;
    final Future<BranchDetails> branchDetails;

    /**
     * Communication layer between the real service, when it finally is
     * initialized, and whatever it may be, and this outer component.
     */
    final MediaStatusServiceConnector mediaStatusService;

    final QuipPaging quipPaging;

    bool get noQuips => quipPaging.quips.length <= 0;

    BranchDetails _branchDetails;
    final int branchId;
    final int urlChangeId;

    bool _loadError = false;
    bool get loadError => _loadError;

    bool get loaded => _branchDetails != null;
    int get filmId => _branchDetails == null ? null :
            _branchDetails.filmId;
    int get filmReleaseYear => _branchDetails == null ? null :
            _branchDetails.filmReleaseYear;
    String get filmName => _branchDetails == null ? null :
            _branchDetails.filmName;
    String get filmCreatedOn => _branchDetails == null ? null :
            _branchDetails.filmCreatedOn;
    String get filmLastUpdatedOn => _branchDetails == null ? null :
            _branchDetails.filmLastUpdatedOn;
    int get changeId => _branchDetails == null ? urlChangeId :
            _branchDetails.changeId;
    String get branchName => _branchDetails == null ? null :
            _branchDetails.name;
    String get description => _branchDetails == null ? null :
            _branchDetails.description;
    String get updatedOn => _branchDetails == null ? null :
            _branchDetails.updatedOn;
    bool get canReadQuips => _branchDetails == null ? false :
            _branchDetails.userCanReadQuips;
    bool get canEditHeader => _branchDetails == null ? false :
            _branchDetails.userCanEditBranch;
    bool get canEditQuips => _branchDetails == null ? false :
            (_branchDetails.userCanEditQuips ||
                    _branchDetails.userCanEditQuipTags);
    bool get canDelete => _branchDetails == null ? false :
            _branchDetails.userCanDeleteBranch;


    factory ViewBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        int branchId = int.parse(routeProvider.parameters['branchId']);
        int changeId = int.parse(routeProvider.parameters['changeId']);

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        QuipPaging quips = new QuipPaging(server, branchId, changeId);

        return new ViewBranchComponent.direct(server, user, branchId, changeId,
                branchDetails, quips);
    }

    ViewBranchComponent.direct(this._server, this._user, this.branchId,
            this.urlChangeId, this.branchDetails, this.quipPaging) :
            this.mediaStatusService = new MediaStatusServiceConnector() {
        branchDetails.then((BranchDetails bd) {
            if (bd == null) {
                // Either an error or there is no such branch.
                _loadError = true;
                _branchDetails = null;
            } else {
                _branchDetails = bd;
                _loadError = false;
            }
        });
    }

    /**
     * Reload the branch header details.
     */
    Future<BranchDetails> reloadDetails() {
        // Only force a reload if we have a first load
        if (_branchDetails != null) {
            return _branchDetails.updateFromServer(_server)
                .then((ServerResponse response) {
                    return _branchDetails;
                });
        } else {
            return branchDetails;
        }
    }
}

