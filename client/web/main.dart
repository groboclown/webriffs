library webriffs_client;

import 'package:angular/angular.dart';
import 'package:angular/application_factory.dart';
import 'package:logging/logging.dart';

import 'package:webriffs_client/routing/webriffs_router.dart';
import 'package:webriffs_client/component/pageheader_component.dart';
import 'package:webriffs_client/component/errorstatus_component.dart';
import 'package:webriffs_client/component/asyncstatus_component.dart';
import 'package:webriffs_client/component/auth/createuser_component.dart';
import 'package:webriffs_client/component/auth/forgotpassword_component.dart';
import 'package:webriffs_client/component/auth/login_component.dart';
import 'package:webriffs_client/component/film/filmlist_component.dart';
import 'package:webriffs_client/component/film/createfilm_component.dart';
import 'package:webriffs_client/component/film/viewfilm_component.dart';
import 'package:webriffs_client/component/film/filminfoedit_component.dart';
import 'package:webriffs_client/component/branch/createbranch_component.dart';
import 'package:webriffs_client/component/branch/editbranch_component.dart';
import 'package:webriffs_client/component/branch/viewbranch_component.dart';
import 'package:webriffs_client/component/branch/playbranch_component.dart';
import 'package:webriffs_client/component/branch/branchupdates_component.dart';
import 'package:webriffs_client/component/media/media_component.dart';
import 'package:webriffs_client/component/media/stopwatch_media_component.dart';
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
        bind(BranchUpdatesComponent);
        bind(MediaComponent);
        bind(StopwatchMediaComponent);

        // Stateful Services
        bind(ServerStatusService);
        bind(UserService);

        // Routes
        bind(RouteInitializerFn, toValue: webRiffsRouteInitializer);

        // Make sure the full path and query are used by the router.
        bind(NgRoutingUsePushState, toFactory:
            () => new NgRoutingUsePushState.value(false));
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
