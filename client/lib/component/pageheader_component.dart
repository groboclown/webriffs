library pagetitle_component;


import 'dart:html';

import 'package:angular/angular.dart';
import 'package:angular/routing/module.dart';
import 'package:logging/logging.dart';


@Component(
    selector: 'page-header',
    templateUrl: 'packages/webriffs_client/component/pageheader_component.html',
    publishAs: 'cmp')
class PageHeaderComponent implements DetachAware {
    final Logger _log = new Logger('components.PageHeaderComponent');

    RouteHandle _route;

    String titleSuffix = " - WebRiffs";
    String defaultTitle = "WebRiffs";

    String name;

    String title;

    PageHeaderComponent(RouteProvider routeProvider) {
        _onEnter(routeProvider.route);

        _route = routeProvider.route.newHandle();
        _route.onEnter.listen((RouteEvent e) {
            _onEnter(e.route);
        });
    }

    void detach() {
        // The route handle must be discarded.
        _route.discard();
    }


    void _onEnter(Route route) {
        if (route.name == null) {
            name = '';
            title = defaultTitle;
        } else {
            title = route.name + titleSuffix;
            name = route.name;
        }

        // Major, major hack
        // Can't find another way to set the document title.
        querySelector('title').text = title;
    }
}
