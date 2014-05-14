library recipe_book_routing;

import 'package:angular/angular.dart';

void webRiffsRouteInitializer(Router router, RouteViewFactory view) {
    // The router is used with the Page* components.

    router.root
        ..addRoute(
                defaultRoute: true,
                name: 'Welcome - WebRiffs',
                path: '/',
                enter: view('./view/home.html'))
        ..addRoute(
                name: 'Create User - WebRiffs',
                path: '/user/create',
                enter: view('./view/create_user.html'))
        ..addRoute(
                name: 'Login - WebRiffs',
                path: '/user/login',
                enter: view('./view/'))
    ;
}
