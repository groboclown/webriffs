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
    PageService _page;

    String get name => _page.currentPage;

    @NgOneWay('title')
    String get title => name == null ? "WebRiffs" : (name + " - WebRiffs");

    PageTitleComponent(this._page);
}


@Component(
    selector: 'page-header',
    templateUrl: 'packages/webriffs_client/component/pageheader_component.html',
    publishAs: 'cmp')
class PageHeaderComponent {
    PageService _page;

    String get name => _page.currentPage;

    @NgOneWay('title')
    String get title => name == null ? "" : name;

    PageHeaderComponent(this._page);
}

