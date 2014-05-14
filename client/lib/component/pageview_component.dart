
library pageview_component;

import 'package:angular/angular.dart';
import '../service/page.dart';


/**
 * Simple top-level component for ensuring the logic behind the
 * page transitions works as expected.
 */
@Component(
    selector: 'pageview',
    templateUrl: 'packages/webriffs_client/component/pageview_component.html',
    publishAs: 'cmp')
class PageViewComponent {
    PageViewComponent(RouteProvider routeProvider, PageService page) {
        page.route(routeProvider);
    }
}
