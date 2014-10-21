
library forgotpassword_component;


import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';

/**
 * The UI component view of the "forgot my password" form.
 */
@Component(
    selector: 'forgot-my-password',
    templateUrl: 'forgotpassword_component.html'
     //cssUrl: 'forgotpassword_component.css'
    )
class ForgotPasswordComponent {
    UserService userService;

    ForgotPasswordComponent(this.userService);
}

