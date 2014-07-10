library webriffs_router;

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
        ..addRoute(
                name: 'Film Details',
                path: '/film/view/:filmId',
                enter: view('./view/view_film.html'))
        ..addRoute(
                name: 'Edit Your Branch',
                path: '/branch/edit/:branchId',
                enter: view('./view/edit_filmbranch.html'))
        ..addRoute(
                name: 'View Branch',
                path: '/branch/view/:branchId/:changeId',
                enter: view('./view/view_filmbranch.html'))
        ..addRoute(
                name: 'Playback',
                path: '/branch/play/:branchId/:changeId',
                enter: view('./view/play_filmbranch.html'))
        ..addRoute(
                name: 'Branch Changes',
                path: '/branch/changes/:branchId',
                enter: view('./view/changes_filmbranch.html'))
        ..addRoute(
                name: 'Create a Branch',
                path: '/branch/create/:filmId',
                enter: view('./view/create_filmbranch.html'))
        ;
}
