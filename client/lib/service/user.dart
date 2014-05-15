
library user_service;

import 'dart:async';
import 'dart:convert';

import 'package:angular/angular.dart';

import 'server.dart';
import 'error.dart';

/**
 * Corresponds to API requests to the Authentication.php file.
 */
@Injectable()
class UserService extends AbstractServerService{
    Future _loaded;

    UserInfo info;
    bool loggedIn = false;


    UserService(Http http, ErrorService error) : super(http, error) {
        _loaded = Future.wait([loadUserDetails()]);
    }


    Future<ServerResponse> loadUserDetails() {
        return post('/authentication/current')
            .then((ServerResponse response) {
                if (response.wasError) {
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
        return post('/authentication/login', req.toJson())
            .then((ServerResponse response) {
                if (! response.wasError) {
                    loggedIn = true;
                    return loadUserDetails();
                }
                return response;
            });
    }


    Future<ServerResponse> logout() {
        return post('/authentication/logout')
            .then((ServerResponse response) {
                if (response.wasError) {
                    loggedIn = false;
                    info = null;
                }
                return response;
            });
    }


    Future<ServerResponse> createUser(String username, String password,
                                      String contact) {
        var req = new UserCreationRequest(username, password, contact);
        return put('/authentication/create', req.toJson())
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
