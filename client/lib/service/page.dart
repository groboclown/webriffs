library page_service;

import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

/**
 * Keeps track of the current page, for use outside the page.
 * Components stored outside a routing view cannot obtain the current
 * routing information, so they must rely on a hack like this.
 */
@Injectable()
class PageService {
    String _currentPage = "Home";

    UrlMatcher _path;

    String get currentPage => _currentPage;

    UrlMatcher get currentPath => _path;

    void pageChanged(String newPage, UrlMatcher newPath) {
        if (newPage == null) {
            _currentPage = "Home";
        } else {
            _currentPage = newPage;
        }
        _path = newPath;

        // Can fire listener events
    }


    void route(RouteProvider routeProvider) {
        pageChanged(routeProvider.route.name,
            routeProvider.route.path);
    }
}
