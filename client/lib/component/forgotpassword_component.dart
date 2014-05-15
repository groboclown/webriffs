
library forgotpassword_component;


import 'package:angular/angular.dart';

import '../service/error.dart';
import '../service/user.dart';

/**
 * The UI component view of the "forgot my password" form.
 */
@Component(
    selector: 'forgot-my-password',
    templateUrl: 'packages/webriffs_client/component/forgotpassword_component.html',
     //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class ForgotPasswordComponent {
    NgModel _ngModel;

    ErrorService error;

    UserService userService;

    ForgotPasswordComponent(this._ngModel, this.error, this.userService) {
        // FIXME HAAAAAAAAAAACK
        if (_ngModel == null) {
            throw new Exception("null ngModel");
        }
        if (_ngModel.modelValue != null) {
            // FIXME
            //if (!(_ngModel.modelValue is ErrorService)) {
            //    throw new Exception("model value is not ErrorService");
            //}
        } else {
            // FIXME
            //_ngModel.modelValue = error;
        }
    }
}

