
library abstract_branch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../json/branch_details.dart';


/**
 * Generic parts of a UI component that interacts with the branch details.
 * Contains helpers for the branch loading and user permissions.
 */
abstract class AbstractBranchComponent {
    final ServerStatusService _server;
    final UserService _user;
    final Future<BranchDetails> branchDetails;

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
    bool get canEditPermissions => _branchDetails == null ? false :
            _branchDetails.userCanEditBranchPermissions;

    static List<int> parseRouteParameters(RouteProvider routeProvider) {
        var ret = <int>[ -1, null ];
        if (routeProvider.parameters.containsKey('branchId')) {
            ret[0] = int.parse(routeProvider.parameters['branchId']);
        }
        if (routeProvider.parameters.containsKey('changeId')) {
            ret[1] = int.parse(routeProvider.parameters['changeId']);
        }
        return ret;
    }


    AbstractBranchComponent(this._server, this._user, this.branchId,
            this.urlChangeId, this.branchDetails) {
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

        // Reload the details about the branch if the user login state changes.
        // This allows the UI to properly display when the user permissions
        // change.  Note that the branch user permissions are only loaded with
        // the branch details, so this is necessary to keep the two in sync.
        _user.createUserChangedEventStream().forEach((_) {
            reloadDetails();
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

