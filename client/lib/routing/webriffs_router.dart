library recipe_book_routing;

import 'package:angular/angular.dart';

void webRiffsRouteInitializer(Router router, RouteViewFactory view) {
    router.root
        ..addRoute(
                name: 'createUser',
                path: '/user/create',
                enter: view('./view/create_user.html'))
        ..addRoute(
                name: 'login',
                path: 'user/login',
                enter: view('./view/')
    )
    ;
}
