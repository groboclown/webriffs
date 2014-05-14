library pagetitle_component;


import 'package:angular/angular.dart';
import 'package:angular/routing/module.dart';
import 'package:logging/logging.dart';

import '../service/page.dart';

@Component(
    selector: 'page-title',
    templateUrl: 'packages/webriffs_client/component/pagetitle_component.html',
    publishAs: 'cmp')
class PageTitleComponent {
    final Logger _log = new Logger('components.PageTitleComponent');
    @NgOneWay('name')
    String name;

    @NgOneWay('title')
    String title = "WebRiffs";

    PageTitleComponent(PageService page) {
        page.addListener((p) {
            name = p.currentPage;
            title = name == null ? "WebRiffs" : (name + " - WebRiffs");
            _log.info("Set the title to [$title]");
        });
    }
}


@Component(
    selector: 'page-header',
    templateUrl: 'packages/webriffs_client/component/pageheader_component.html',
    publishAs: 'cmp')
class PageHeaderComponent {
    final Logger _log = new Logger('components.PageTitleComponent');
    @NgOneWay('name')
    String name;

    @NgOneWay('title')
    String title;

    PageHeaderComponent(PageService page) {
        page.addListener((p) {
            name = p.currentPage;
            title = name == null ? "" : name;
            _log.info("Set the title to [$title]");
        });
    }
}

