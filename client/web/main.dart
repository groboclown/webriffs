library webriffs_client;

import 'package:angular/angular.dart';
import 'package:angular/application_factory.dart';
import 'package:logging/logging.dart';

import 'package:webriffs_client/routing/webriffs_router.dart';
import 'package:webriffs_client/component/pageheader_component.dart';
import 'package:webriffs_client/component/errorstatus_component.dart';
import 'package:webriffs_client/component/createuser_component.dart';
import 'package:webriffs_client/component/forgotpassword_component.dart';
import 'package:webriffs_client/component/login_component.dart';
import 'package:webriffs_client/component/filmlist_component.dart';
import 'package:webriffs_client/service/error.dart';
import 'package:webriffs_client/service/user.dart';


class WebRiffsModule extends Module {
    WebRiffsModule() {
        // Components
        bind(ErrorComponent);
        bind(PageHeaderComponent);
        bind(CreateUserComponent);
        bind(ForgotPasswordComponent);
        bind(LoginComponent);
        bind(FilmListComponent);

        // Stateful Services - that's why they're value, not type
        bind(ErrorService);
        bind(UserService);

        // Routes
        bind(RouteInitializerFn, toValue: webRiffsRouteInitializer);

        // Make sure the full path and query are used by the router.
        bind(NgRoutingUsePushState, toFactory:
            (_) => new NgRoutingUsePushState.value(false));
    }
}



void main() {
    Logger.root
        ..level = Level.FINEST
        ..onRecord.listen((LogRecord r) {
        print(r.message);
    });

    applicationFactory()
        .addModule(new WebRiffsModule())
        .run();
}
