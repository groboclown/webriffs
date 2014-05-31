
library film_service;

import 'dart:async';

import 'package:angular/angular.dart';

import 'server.dart';



/**
 * Corresponds to API requests to the Film.php file.
 */
@Injectable()
class FilmService {
    ServerStatusService _server;

    bool loggedIn = false;


    FilmService(this._server);




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
