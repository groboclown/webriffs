
library user_service;

import 'dart:async';

import 'package:angular/angular.dart';

import 'error.dart';

/**
 * Corresponds to API requests to the Authentication.php file.
 */
@Injectable()
class UserService {
    final Http _http;
    final ErrorService _error;

    Future _loaded;

    UserService(this._http, this._error) {
        _loaded = Future.wait([_loadUserDetails()]);
    }


    Future _loadUserDetails() {
        return _http.post('api/authentication/current', '{}')
            .then((HttpResponse response) {
                // response status is 200-299
            }, onError: (HttpResponse request) {
                _error.addHttpRequestError(request);
            }).catchError((Exception e) {
                _error.addHttpRequestException(e);
            });
    }
}






class LoginResult {
    final String username;
    final String password;
    final String source = 'local';

    LoginResult(this.username, this.password);


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
