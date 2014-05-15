
library user_service;

import 'dart:async';
import 'dart:convert';

import 'package:angular/angular.dart';

import 'error.dart';

/**
 * Corresponds to API requests to the Authentication.php file.
 *
 * FIXME make another service that handles API requests to the server
 * in a generic manner.
 */
@Injectable()
class UserService {
    final Http _http;
    final ErrorService _error;
    final Map<String, dynamic> _headers;

    Future _loaded;

    UserInfo info;
    bool loggedIn = false;


    UserService(this._http, this._error) : _headers = {} {
        _headers['Content-Type'] = 'application/json';

        _loaded = Future.wait([loadUserDetails()]);
    }


    Future<ServerResponse> loadUserDetails() {
        return _http.post('api/authentication/current', '{}')
            .then((HttpResponse resp) {
                ServerResponse response = _error.processResponse(resp);
                if (response != null && ! response.wasError) {
                    // response status is 200-299
                    loggedIn = true;
                    info = new UserInfo.fromJson(response.jsonData);
                }
                return response;
            }, onError: (HttpResponse response) {
                loggedIn = false;
                return _error.processResponse(response);
            }).catchError((Exception e) {
                loggedIn = false;
                return _error.addHttpRequestException(e);
            });
    }


    Future<ServerResponse> login(String username, String password) {
        var req = new LoginRequest(username, password);
        return _http.post('api/authentication/login',
            JSON.encode(req.toJson()))
            .then((HttpResponse resp) {
                loggedIn = true;
                ServerResponse response = _error.processResponse(resp);
                if (response != null && ! response.wasError) {
                    // future chaining
                    return loadUserDetails();
                }
                return response;
            },
            onError: (HttpResponse response) {
                loggedIn = false;
                return _error.processResponse(response);
            })
            .catchError((Exception e) {
                loggedIn = false;
                return _error.addHttpRequestException(e);
            });
    }


    Future<ServerResponse> logout() {
        return _http.post('api/authentication/logout', '{}')
            .then((HttpResponse resp) {
                loggedIn = false;
                ServerResponse response = _error.processResponse(resp);
                if (response != null && ! response.wasError) {
                    info = null;
                }
                return response;
            },
            onError: (HttpResponse response) {
                loggedIn = false;
                return _error.processResponse(response);
            })
            .catchError((Exception e) {
                // yes, we're still logged in.
                loggedIn = true;
                return _error.addHttpRequestException(e);
            });
    }


    Future<ServerResponse> createUser(String username, String password,
                                      String contact) {
        var req = new UserCreationRequest(username, password, contact);
        return _http.post('api/authentication/create',
                JSON.encode(req.toJson()))
            .then((HttpResponse resp) {
                loggedIn = false;
                ServerResponse response = _error.processResponse(resp);
                if (response != null && !response.wasError) {
                    info = null;
                }
                return response;
            }, onError: (HttpResponse response) {
                loggedIn = false;
                return _error.processResponse(response);
            }).catchError((Exception e) {
                loggedIn = false;
                return _error.addHttpRequestException(e);
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
