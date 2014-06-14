library webriffs_client;

import 'package:angular/angular.dart';
import 'package:angular/application_factory.dart';
import 'package:logging/logging.dart';

import 'package:webriffs_client/routing/webriffs_router.dart';
import 'package:webriffs_client/component/pageheader_component.dart';
import 'package:webriffs_client/component/errorstatus_component.dart';
import 'package:webriffs_client/component/asyncstatus_component.dart';
import 'package:webriffs_client/component/createuser_component.dart';
import 'package:webriffs_client/component/forgotpassword_component.dart';
import 'package:webriffs_client/component/login_component.dart';
import 'package:webriffs_client/component/filmlist_component.dart';
import 'package:webriffs_client/component/createfilm_component.dart';
import 'package:webriffs_client/component/viewfilm_component.dart';
import 'package:webriffs_client/component/createbranch_component.dart';
import 'package:webriffs_client/component/editbranch_component.dart';
import 'package:webriffs_client/component/viewbranch_component.dart';
import 'package:webriffs_client/component/playbranch_component.dart';
import 'package:webriffs_client/component/filminfoedit_component.dart';
//import 'package:webriffs_client/component/_component.dart';
import 'package:webriffs_client/service/server.dart';
import 'package:webriffs_client/service/user.dart';


class WebRiffsModule extends Module {
    WebRiffsModule() {
        // Components
        bind(PageHeaderComponent);
        bind(ErrorComponent);
        bind(AsyncStatusComponent);
        bind(CreateUserComponent);
        bind(ForgotPasswordComponent);
        bind(LoginComponent);
        bind(FilmListComponent);
        bind(CreateFilmComponent);
        bind(ViewFilmComponent);
        bind(EditBranchComponent);
        bind(ViewBranchComponent);
        bind(PlayBranchComponent);
        bind(CreateBranchComponent);
        bind(FilmInfoEditComponent);

        // Stateful Services
        bind(ServerStatusService);
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
