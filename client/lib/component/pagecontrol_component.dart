library pagecontrol_component;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../util/paging.dart';

@Component(
    selector: 'page-control',
    templateUrl: 'pagecontrol_component.html')
class PageControlComponent {
    final Logger _log = new Logger('components.PageHeaderComponent');

    @NgOneWayOneTime('page-state')
    PageState pageState;


    @NgOneWay('pages-shown')
    int pageSelectCount;


    void gotoDisplayPage(int pageNumber) {
        if (pageNumber >= 1 && pageNumber <= pageState.pageCount) {
            pageState.updateFromServer(nextPage: pageNumber - 1);
        }
    }


    void gotoFirstPage() {
        if (pageState.currentPage > 0) {
            pageState.updateFromServer(nextPage: 0);
        }
    }


    void gotoLastPage() {
        if (pageState.currentPage < pageState.pageCount - 1) {
            pageState.updateFromServer(nextPage: pageState.pageCount - 1);
        }
    }


    List<int> getDisplayPages() {
        // TODO show at most pageSelectCount pages
        List<int> ret = [];
        for (int i = 0; i < pageState.pageCount; ++i) {
            ret.add(i + 1);
        }
        return ret;
    }


    bool get isFirstPage => pageState.currentPage <= 0;

    bool get isLastPage => pageState.currentPage >= pageState.pageCount - 1;

    bool get hasTwoNext => pageState.currentPage + 1 < pageState.pageCount;

    bool get hasTwoPrev => pageState.currentPage >= 1;


    void gotoPreviousPage() {
        if (pageState.currentPage > 0) {
            pageState.updateFromServer(nextPage: pageState.currentPage - 1);
        }
    }


    void gotoNextPage() {
        if (pageState.currentPage < pageState.pageCount - 1) {
            pageState.updateFromServer(nextPage: pageState.currentPage + 1);
        }
    }
}
