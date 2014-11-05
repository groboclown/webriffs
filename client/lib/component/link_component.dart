library link_component;

import 'dart:html';
import 'package:angular/angular.dart';

@Component(
    selector: 'href',
    useShadowDom: false,
    template: '<a href="#" ng-click="redirect(\$event)"><content></content></a>')
class LinkHrefComponent {
    final Router _route;

    @NgOneWayOneTime('route')
    String routePath;

    // All the parameters defined in our route.
    @NgOneWayOneTime('branchId')
    int branchId;

    @NgOneWayOneTime('changeId')
    int changeId;

    @NgOneWayOneTime('filmId')
    int filmId;

    @NgOneWayOneTime('username')
    String username;





    LinkHrefComponent(this._route);

    void redirect(MouseEvent event) {
        Map<String, dynamic> routeArgs = {};
        if (branchId != null) {
            routeArgs['branchId'] = branchId;
        }
        if (changeId != null) {
            routeArgs['changeId'] = changeId;
        }
        if (filmId != null) {
            routeArgs['filmId'] = filmId;
        }
        if (username != null) {
            routeArgs['username'] = username;
        }

        event.preventDefault();
        event.stopPropagation();

        _route.go(routePath, routeArgs);
    }
}
