library page_service;

import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

typedef PageListener(PageService);

/**
 * Keeps track of the current page, for use outside the page.
 * Components stored outside a routing view cannot obtain the current
 * routing information, so they must rely on a hack like this.
 */
@Injectable()
class PageService {
    final Logger _log = new Logger('service.PageService');

    String _currentPage = "Home";

    UrlMatcher _path;

    String get currentPage => _currentPage;

    UrlMatcher get currentPath => _path;

    List<PageListener> listeners = new List<PageListener>();

    void pageChanged(String newPage, UrlMatcher newPath) {
        if (newPage == null) {
            _currentPage = "Home";
        } else {
            _currentPage = newPage;
        }
        _path = newPath;

        _onUpdate();
    }


    void addListener(PageListener listener) {
        if (listener != null) {
            listener(this);
            listeners.add(listener);
        }
    }


    void route(RouteProvider routeProvider) {
        pageChanged(routeProvider.route.name,
            routeProvider.route.path);
    }


    void _onUpdate() {
        _log.info("Set page to " + _currentPage + " path " + _path.toString());
        listeners.forEach((l) => l(this));
    }
}
