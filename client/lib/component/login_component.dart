
library login_component;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../service/error.dart';
import '../service/user.dart';
import '../login.dart';

/**
 * The UI component view of the login form.
 */
@Component(
    selector: 'forgot-my-password',
    templateUrl: 'packages/webriffs_client/component/forgotpassword_component.html',
     //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class LoginComponent {
    final Logger _log = new Logger('components.CreateUserComponent');

    NgModel _ngModel;

    UserService userService;
    ErrorService _error;

    LoginComponent(this._ngModel, this._error, this.userService) {
        // FIXME HAAAAAAAAAAACK
        if (_ngModel == null) {
            throw new Exception("null ngModel");
        }
        if (_ngModel.modelValue != null) {
            _log.severe("model already has a value: " + _ngModel.modelValue.toString());
            if (!(_ngModel.modelValue is LoginModel)) {
                _log.severe("NOT A CreateUser!!!!");
            }
        } else {
            _ngModel.modelValue = new LoginModel();
            _log.severe("had to create our own new model value");
        }
    }
}

