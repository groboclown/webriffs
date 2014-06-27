library pagetitle_component;


import 'dart:html';

import 'package:angular/angular.dart';
import 'package:angular/routing/module.dart';
import 'package:logging/logging.dart';
import '../service/user.dart';

@Component(
    selector: 'page-header',
    templateUrl: 'packages/webriffs_client/component/pageheader_component.html',
    publishAs: 'cmp')
class PageHeaderComponent implements DetachAware {
    final Logger _log = new Logger('components.PageHeaderComponent');

    RouteHandle _route;
    UserService _user;

    bool get isLoggedIn => _user.loggedIn;

    bool get canCreateFilm => _user.canCreateFilms;
    bool get canCreateBranch => _user.canCreateBranch;

    String titleSuffix = " - WebRiffs";
    String defaultTitle = "WebRiffs";

    String name;

    String title;

    String branchId;
    bool get hasBranchOnPage => branchId != null;
    bool get showForkLink => canCreateBranch && hasBranchOnPage;

    String filmId;
    bool get hasFilmOnPage => filmId != null;
    bool get showBranchLink => canCreateBranch && hasFilmOnPage;


    PageHeaderComponent(RouteProvider routeProvider, this._user) {
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
        String n;
        String t;
        if (route.name == null) {
            t = defaultTitle;
            n = '';
        } else {
            t = route.name + titleSuffix;
            n = route.name;
        }

        // Allow for parameterized names
        route.parameters.forEach((String k, dynamic v) {
            n = n.replaceAll('<${k}>', v.toString());
            t = t.replaceAll('<${k}>', v.toString());

            if (k == "branchId" && v != null) {
                branchId = v.toString();
            } else if (k == "filmId" && v != null) {
                filmId = v.toString();
            }
            //print("Found parameter [${k}]=[${v}]");
        });

        title = t;
        name = n;

        // Major, major hack
        // Can't find another way to set the document title.
        querySelector('title').text = title;
    }
}

