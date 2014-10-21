
library createbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../util/async_component.dart';

/**
 * The UI component to create a new branch.
 */
@Component(
    selector: 'create-branch',
    templateUrl: 'createbranch_component.html')
class CreateBranchComponent extends RequestHandlingComponent {
    final ServerStatusService _server;
    final UserService _user;
    final Router _route;
    final LookUpBranchNameComponent lookupName;

    AsyncComponent get cmp => this;

    int _filmId;

    int get filmId => _filmId;

    @NgOneWayOneTime('film-id')
    set filmId(int i) {
        _filmId = i;
        lookupName.filmId = i;
    }

    @NgOneWayOneTime('always-show')
    bool alwaysShowCreate = false;

    bool _showCreateToggle = false;

    bool get showCreateBranch => _showCreateToggle || alwaysShowCreate;

    set showCreateBranch(bool s) {
        _showCreateToggle = s;
    }

    bool get canCreateBranch => _user.canCreateBranch;

    bool get disabled => filmId != null && ! lookupName.okayToSubmit;



    CreateBranchComponent(ServerStatusService server, this._user,  this._route,
            RouteProvider routeProvider) :
            _server = server,
            lookupName = new LookUpBranchNameComponent(server)
    {
        if (routeProvider.parameters.containsKey('filmId')) {
            filmId = int.parse(routeProvider.parameters['filmId']);
        }
    }

    void cancel() {
        showCreateBranch = false;
        lookupName.reset();
    }

    void createBranch() {
        if (! disabled) {
            lookupName.valid = false;
            csrfRequest(_server, 'create_branch',
                    (ServerStatusService server, String token) {
                return _server.put('/film/' + filmId.toString() + '/branch',
                        token, data: {
                            'Name': lookupName.name
                            // 'Description'
                        });
            });
        } else {
            print("current branch name is not valid.");
        }
    }

    @override
    Future<ServerResponse> onSuccess(ServerResponse response) {
        // Reset the fields
        showCreateBranch = false;
        lookupName.reset();

        int branchId = response.jsonData['Branch_Id'];
        int changeId = response.jsonData['Change_Id'];

        // redirect to the edit page
        _route.go('Edit Your Branch', {
            'branchId': branchId
        });
        return null;
    }

    @override
    void reload() {
        // do nothing
    }
}


class LookUpBranchNameComponent extends SingleRequestComponent {
    final ServerStatusService _server;
    int filmId;
    String _lastValidName;
    String _name;
    bool valid = false;
    bool checking = false;
    bool get okayToSubmit => valid && ! checking;

    String get name => _name;
    set name(String n) {
        if (n == null || n.length <= 0) {
            valid = false;
            return;
        }
        if (n == _lastValidName) {
            valid = true;
            _name = n;
            return;
        }
        if (n != _name) {
            valid = false;
            _name = n;
            reload();
        }
    }


    LookUpBranchNameComponent(ServerStatusService server) :
        _server = server,
        super(server);



    @override
    Future<ServerResponse> onSuccess(ServerResponse response) {
        checking = false;
        if (response.jsonData != null &&
                response.jsonData.containsKey('exists')) {
            valid = response.jsonData['exists'] == false;
        } else {
            valid = false;
        }
        return null;
    }

    @override
    void reload() {
        valid = false;
        checking = true;
        String path = "/film/" + filmId.toString() + "/branchexists?Name=" +
                Uri.encodeQueryComponent(_name);
        get(path);
    }

    @override
    void onError(Exception e) {
        valid = false;
        checking = false;
        super.onError(e);
    }

    void reset() {
        _name = null;
        _lastValidName = null;
        valid = false;
        checking = false;
    }
}
