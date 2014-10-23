
library login_component;

import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/server.dart';
import '../../service/user.dart';

/**
 * The UI component view of the login form.
 */
@Component(
    selector: 'login',
    templateUrl: 'packages/webriffs_client/component/auth/login_component.html'
    //cssUrl: 'login_component.css'
    )
class LoginComponent {
    final Logger _log = new Logger('components.LoginComponent');

    UserService _user;

    bool get loggedIn => _user.loggedIn;

    UserInfo get info => _user.info;

    String get loggedInUser => _user.info.username;


    String _username;
    String _password;

    String usernameError;

    String get username => _username;

    set username(String un) {
        if (un == null || un.length < 3) {
            usernameError = "username length must be at least 3 characters";
        }
        // FIXME validate alphanum + (_ -) characters
        else {
            usernameError = null;
        }
        //_log.finest("Setting username to [$un] with error [$usernameError]");
        _username = un;
    }


    String passwordError;

    String get password => _password;

    set password(String pw) {
        if (pw == null || pw.length <= 0) {
            passwordError = "must set a password";
        } else {
            passwordError = null;
        }
        //_log.finest("Setting password to [$pw] with error [$passwordError]");
        _password = pw;
    }

    LoginComponent(this._user) {
        username = null;
        password = null;
    }


    bool hasError() {
        return (usernameError != null || passwordError != null);
    }


    Future<ServerResponse> submit() {
        if (! hasError() && ! loggedIn) {
            return _user.login(username, password).
            then((ServerResponse response) {
                // clear the password field
                password = null;
                return response;
            });
        }
        return null;
    }


    Future<ServerResponse> logOut() {
        if (loggedIn) {
            return _user.logout();
        }
        return null;
    }
}

