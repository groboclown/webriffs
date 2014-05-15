library createuser_component;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../service/user.dart';
import '../service/error.dart';
import '../createuser.dart';

@Component(
    selector: 'create-user',
    templateUrl: 'packages/webriffs_client/component/createuser_component.html',
    publishAs: 'cmp')
class CreateUserComponent {
    final Logger _log = new Logger('components.CreateUserComponent');

    NgModel _ngModel;

    UserService _user;
    ErrorService _error;

    CreateUserComponent(this._ngModel, this._user, this._error) {
        // FIXME HAAAAAAAAAAACK
        if (_ngModel == null) {
            throw new Exception("null ngModel");
        }
        if (_ngModel.modelValue != null) {
            _log.severe("model already has a value: " + _ngModel.modelValue.toString());
            if (! (_ngModel.modelValue is CreateUserModel)) {
                _log.severe("NOT A CreateUser!!!!");
            }
        } else {
            _ngModel.modelValue = new CreateUserModel();
            _log.severe("had to create our own new model value");
        }
    }


    void submit() {
        if (hasError()) {
            _log.info("error - cannot sumit");
        } else {
            _log.info("sumit data: contact = [" +
                value.contact + "], username = [" +
                value.username + "], password = [" +
                value.password + "], password-match = [" +
                value.passwordMatch + "]");
            // submit
        }
    }


    bool hasError() {
        return value.hasError();
    }


    CreateUserModel get value => _ngModel.modelValue;
}

