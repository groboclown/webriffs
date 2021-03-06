library createuser_component;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/user.dart';
import '../../service/server.dart';

@Component(
    selector: 'create-user',
    templateUrl: 'packages/webriffs_client/component/auth/createuser_component.html')
class CreateUserComponent {
    final Logger _log = new Logger('components.CreateUserComponent');

    UserService _user;
    ServerStatusService _error;

    bool get loggedIn => _user.loggedIn;

    bool _createdUser = false;

    bool get createdUser => _createdUser;

    String _username;
    String _password;
    String _passwordMatch;
    String _contact;


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
        _username = un;
    }


    String passwordError;

    String get password => _password;

    set password(String pw) {
        _checkPasswordAndMatch(pw, passwordMatch);
        _password = pw;
    }


    String passwordMatchError;

    String get passwordMatch => _passwordMatch;

    set passwordMatch(String pm) {
        _checkPasswordAndMatch(password, pm);
        _passwordMatch = pm;
    }


    String contactError;

    String get contact => _contact;

    set contact(String ct) {
        if (ct == null || ct.length <= 0) {
            contactError = "Must supply contact information";
        }
        // FIXME email validation
        else {
            contactError = null;
        }
        //_log.finest("Setting contact to [$ct] with error [$contactError]");
        _contact = ct;
    }


    bool get hasError =>
        (usernameError != null ||
        passwordError != null ||
        contactError != null ||
        passwordMatchError != null);


    _checkPasswordAndMatch(pw, pm) {
        if (pw == null || pw.length <= 0) {
            passwordError = "must set a password";
        } else if (pw.length < 6) {
            passwordError = "password length must be at least 6";
        } else
            // FIXME password strength check
        {
            passwordError = null;
        }

        if (pm != pw) {
            passwordMatchError = "passwords do not match";
        } else {
            passwordMatchError = null;
        }
    }


    CreateUserComponent(this._user, this._error) {
        username = null;
        password = null;
        passwordMatch = null;
        contact = null;
    }


    void submit() {
        if (hasError) {
            _log.info("error - cannot sumit");
        } else {
            _log.finest("sumit data: contact = [" +
                contact + "], username = [" +
                username + "], password = [" +
                password + "], password-match = [" +
                passwordMatch + "]");
            // submit
            _user.createUser(username, password,
                contact).
                then((ServerResponse resp) {
                    if (resp.wasError) {
                        // FIXME better error reporting
                        // may just be a feature of the "error" stuff.
                        _log.severe("error communicating to server: " +
                            resp.message.toString());
                    } else {
                        // report success and redirect.
                        _log.fine("created the user!");

                        _createdUser = true;

                        // Clear out the fields
                        username = null;
                        password = null;
                        passwordMatch = null;
                        contact = null;
                    }
                });
        }
    }

}

