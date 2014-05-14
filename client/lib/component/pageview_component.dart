
library pageview_component;

import 'package:angular/angular.dart';
import '../service/page.dart';


/**
 * Abstract top-level component for ensuring the logic behind the
 * page transitions works as expected.
 */
class AbstractPageView {
    AbstractPageView(RouteProvider routeProvider, PageService page) {
        page.route(routeProvider);
    }
}
