library createuser_model;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';


/**
 * Currently, there doesn't seem to be a way to create the
 * model without a controller.  It might be done by registering
 * the model at the top level, but that seems like overkill.
 */
@Controller(
    selector: '[create-user]',
    publishAs: 'ctrl'
)
class CreateUserController {
    CreateUserModel createuser = new CreateUserModel();
}


class CreateUserModel {
    final Logger _log = new Logger('model.CreateUserModel');

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
        //_log.finest("Setting username to [$un] with error [$usernameError]");
        _username = un;
    }


    String passwordError;

    String get password => _password;

    set password(String pw) {
        _checkPasswordAndMatch(pw, passwordMatch);
        //_log.finest("Setting password to [$pw] with error [$passwordError]");
        _password = pw;
    }


    String passwordMatchError;

    String get passwordMatch => _passwordMatch;

    set passwordMatch(String pm) {
        _checkPasswordAndMatch(password, pm);
        //_log.finest("Setting passwordMatch to [$pm] with error [$passwordMatchError]");
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


    CreateUserModel() {
        username = null;
        password = null;
        passwordMatch = null;
        contact = null;
    }


    bool hasError() {
        return (usernameError != null || passwordError != null || contactError != null || passwordMatchError != null);
    }


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

}
