
library branchinfoedit_component;

import 'dart:async';
import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../json/branch_details.dart';

// FIXME this is essentially a clone of FilmInfoEditComponent.  Look at a way
// to extract common logic.


/**
 * The model data used by the edit component, which is passed-in from the
 * parent.  This data structure is essentially the communication point between
 * the child and parent.  This object is controlled by the child.
 * The low-level details behind the error status are
 * hidden from the parent.
 *
 * The parent can communicate to the form to indicate that it's committing
 * the changes by setting the "commit" flag to true.  If this is enabled,
 * the edit component won't allow updates.
 */
class BranchInfo {
    bool hasError = true;
    bool checking = false;
    bool commit = false;

    // FIXME just bring in the details, don't directly reference it.
    final BranchDetails details;

    BranchInfo(this.details);
}


/**
 * The bits of the branch top-level info that need validation against the server.
 */
@Component(
    selector: 'branchinfo-edit',
    templateUrl: 'packages/webriffs_client/component/branch/branchinfoedit_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class BranchInfoEditComponent extends SingleRequestComponent {
    final ServerStatusService _server;

    @NgOneWay('branch-info')
    BranchInfo branchInfo;

    BranchInfoEditComponent(ServerStatusService server) :
        _server = server,
        super(server);

    bool get enabled => ! branchInfo.commit;

    bool get disabled => branchInfo.commit;

    bool _branchInUse = false;

    bool get branchInUse => _branchInUse;

    bool _isNameValid = true;

    bool get isNameValid => _isNameValid;

    bool get hasError => branchInfo.hasError;

    bool get isChecking => branchInfo.checking;

    String get name => branchInfo.details.name;

    String get description => branchInfo.details.description;

    set name(String name) {
        if (branchInfo.details.name != name) {
            validateName(name);
        }
    }


    @override
    void reload() {
        validateName(branchInfo.details.name);
    }



    void validateName(String name) {
        // Completely set the error state flags, and possibly load data
        // from the server if things on this side look fine.

        if (name == null || ! (name is String)) {
            name = name.toString();
            _isNameValid = false;
        } else if (name.length > 0 && name.length < 200) {
            _isNameValid = true;
        } else {
            _isNameValid = false;
        }

        branchInfo.details.name = name;

        if (_isNameValid) {
            _checkIfNameInUse();
        } else {
            branchInfo.hasError = true;
        }

    }



    void _checkIfNameInUse() {
        // we're going to call out to the server to see if the film is in use.
        // for safety, we'll state that there's an issue with the film info,
        // but we won't mark the film as being in use.
        branchInfo.hasError = false;
        branchInfo.checking = true;
        _branchInUse = false;

        String filmId = branchInfo.details.filmId.toString();
        String path = "/film/${filmId}/branchexists?Name=" +
                Uri.encodeQueryComponent(branchInfo.details.name);
        get(path);
    }


    @override
    Future<ServerResponse> onSuccess(ServerResponse response) {
        branchInfo.checking = false;
        if (response.jsonData != null &&
                response.jsonData.containsKey('exists')) {
            _branchInUse = response.jsonData['exists'] == true;
        } else {
            _branchInUse = false;
        }
        branchInfo.hasError = _branchInUse;
        return null;
    }


    @override
    void onError(Exception e) {
        branchInfo.checking = false;
        branchInfo.hasError = true;
        super.onError(e);
    }

}

