
library forgotpassword_component;


import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';

/**
 * The UI component view of the "forgot my password" form.
 */
@Component(
    selector: 'forgot-my-password',
    templateUrl: 'packages/webriffs_client/component/auth/forgotpassword_component.html',
     //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class ForgotPasswordComponent {
    UserService userService;

    ForgotPasswordComponent(this.userService);
}

