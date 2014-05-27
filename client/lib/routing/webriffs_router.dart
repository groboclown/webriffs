library recipe_book_routing;

import 'package:angular/angular.dart';

void webRiffsRouteInitializer(Router router, RouteViewFactory view) {
    // The router is used with the Page* components.

    router.root
        ..addRoute(
                defaultRoute: true,
                name: 'Welcome',
                path: '/\$',
                enter: view('./view/home.html'))
        ..addRoute(
                name: 'Create User',
                path: '/user/create',
                enter: view('./view/create_user.html'))
        ..addRoute(
                name: 'User Created',
                path: '/user/created',
                enter: view('./view/user_created.html'))
        ..addRoute(
                name: 'Forgot Password',
                path: '/user/forgot',
                enter: view('./view/forgot_password.html'))
        ..addRoute(
                name: 'Films',
                path: '/film/list',
                enter: view('./view/film_list.html'))
        ..addRoute(
                name: 'Add a Film',
                path: '/film/create',
                enter: view('./view/create_film.html'))
        ;
}
