
library editbranch_component;


import 'package:angular/angular.dart';

import '../../service/server.dart';

import '../../util/paging.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'edit-branch',
    templateUrl: 'packages/webriffs_client/component/editbranch_component.html',
    publishAs: 'cmp')
class EditBranchComponent {
    final ServerStatusService _server;

    PageState pageState;

    final List<Quip> quips = [];

    bool get noQuips => quips.length <= 0;

    int filmId;
    int branchId;

    EditBranchComponent(this._server, RouteProvider routeProvider) {
        branchId = int.parse(routeProvider.parameters['branchId']);

        pageState = new PageState(this._server, '/branch/',
            (PageState ps, Iterable<dynamic> fl) {
                quips.clear();
                fl.forEach((Map<String, dynamic> json) {
                    quips.add(new Quip.fromJson(json));
                });
            });
    }
}




class Quip {
    String name;
    int releaseYear;
    List<String> branches;
    List<String> tags;

    factory Quip.fromJson(Map<String, dynamic> json) {
        // FIXME

        return new Quip._();
    }


    Quip._() {
        // FIXME
    }
}
