library login_model;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';


/**
 * Currently, there doesn't seem to be a way to create the
 * model without a controller.  It might be done by registering
 * the model at the top level, but that seems like overkill.
 */
@Controller(
    selector: '[login]',
    publishAs: 'ctrl'
)
class LoginController {
    LoginModel login = new LoginModel();
}


class LoginModel {
    final Logger _log = new Logger('model.LoginModel');

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
        }
        //_log.finest("Setting password to [$pw] with error [$passwordError]");
        _password = pw;
    }


    LoginModel() {
        username = null;
        password = null;
    }


    bool hasError() {
        return (usernameError != null || passwordError != null);
    }

}
