library webriffs_client;

import 'package:angular/angular.dart';
import 'package:angular/application_factory.dart';
import 'package:logging/logging.dart';

import 'package:webriffs_client/routing/webriffs_router.dart';
import 'package:webriffs_client/component/route_info_components.dart';
import 'package:webriffs_client/component/error_component.dart';
import 'package:webriffs_client/service/error.dart';
import 'package:webriffs_client/service/page.dart';
import 'package:webriffs_client/service/user.dart';


class WebRiffsModule extends Module {
    WebRiffsModule() {
        // Components
        type(ErrorComponent);
        type(PageTitleComponent);
        type(PageHeaderComponent);

        // Services
        type(ErrorService);
        type(UserService);
        type(PageService);

        // Routes
        value(RouteInitializerFn, webRiffsRouteInitializer);

        // TODO what does this do?
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
