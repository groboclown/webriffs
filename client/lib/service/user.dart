
library user_service;

import 'dart:async';

import 'package:angular/angular.dart';

import 'server.dart';

IsErrorCheckerFunc UNAUTHORIZED_IS_NOT_ERROR = (int code) {
    if (DEFAULT_IS_ERROR_CHECKER_FUNC(code)) {
        // custom unauthorized error code.
        return code != 412;
    }
    return false;
};


/**
 * Corresponds to API requests to the Authentication.php file.  It also keeps
 * track of the current logged in user information.
 */
@Injectable()
class UserService {
    ServerStatusService _server;
    Future _loaded;

    UserInfo info;
    bool loggedIn = false;


    UserService(this._server) {
        _loaded = Future.wait([loadUserDetails()]);
    }


    /**
     * This is used as a kind of ping to ensure that the server still knows us.
     * This needs to be called if any kind of authentication error occurs, as
     * it causes the different  parts of the UI that care about logged-in state
     * to refresh.
     */
    Future<ServerResponse> loadUserDetails() {
        return _server.post('/authentication/current',
                isErrorChecker: UNAUTHORIZED_IS_NOT_ERROR)
            .then((ServerResponse response) {
                if (response.status == 412 || response.wasError) {
                    loggedIn = false;
                    info = null;
                } else {
                    // response status is 200-299
                    loggedIn = true;
                    info = new UserInfo.fromJson(response.jsonData);
                }
                return response;
            });
    }


    Future<ServerResponse> login(String username, String password) {
        var req = new LoginRequest(username, password);
        return _server.post('/authentication/login', data: req.toJson())
            .then((ServerResponse response) {
                // Force a check to see if we are indeed logged in.
                return loadUserDetails();
            });
    }


    Future<ServerResponse> logout() {
        return _server.post('/authentication/logout',
                isErrorChecker: UNAUTHORIZED_IS_NOT_ERROR)
            .then((ServerResponse response) {
                // Force a check to see if we are indeed logged in or not.
                return loadUserDetails();
            });
    }


    Future<ServerResponse> createUser(String username, String password,
                                      String contact) {
        var req = new UserCreationRequest(username, password, contact);
        return _server.put('/authentication/create',
                data: req.toJson())
            .then((ServerResponse response) {
                if (! response.wasError) {
                    loggedIn = false;
                    info = null;
                }
                return response;
            });
    }
}






class LoginRequest {
    final String username;
    final String password;
    final String source = 'local';

    LoginRequest(this.username, this.password);


    Map<String, dynamic> toJson() {
        var ret = new Map<String, dynamic>();
        ret['username'] = username;
        ret['password'] = password;
        ret['source'] = source;
        return ret;
    }
}



class UserInfo {
    final String username;
    final String contact;
    final bool isAdmin;
    final String createdOn;
    final String lastUpdatedOn;

    UserInfo(this.username, this.contact, this.isAdmin, this.createdOn,
            this.lastUpdatedOn);

    factory UserInfo.fromJson(Map<String, dynamic> json) {
        return new UserInfo(
            json['username'],
            json['contact'],
            json['is_admin'],
            json['created_on'],
            json['last_updated_on']);
    }
}


class UserCreationRequest {
    final String username;
    final String password;
    final String contact;
    final String source = 'local';

    UserCreationRequest(this.username, this.password, this.contact);

    Map<String, dynamic> toJson() {
        var ret = new Map<String, dynamic>();
        ret['username'] = username;
        ret['password'] = password;
        ret['contact'] = contact;
        ret['source'] = source;
        return ret;
    }
}
