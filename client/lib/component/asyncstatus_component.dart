library asyncstatus_component;


//import 'dart:html';

import 'package:angular/angular.dart';
//import 'package:logging/logging.dart';

import '../util/async_component.dart';

@Component(
    selector: 'async-status',
    templateUrl: 'packages/webriffs_client/component/asyncstatus_component.html',
    publishAs: 'cmp')
class AsyncStatusComponent {
    @NgOneWay('parent')
    AsyncComponent async;

    @NgOneWay('show-reload')
    bool showReload = false;

    @NgOneWay('show-initial-load')
    bool showInitialLoad = false;

    bool get showLoadButton => (showReload) || (showInitialLoad && notLoaded);

    bool get loadedError => async == null ? true : async.loadedError;
    bool get loadedSuccessful => async == null ? false : async.loadedSuccessful;
    bool get loading => async == null ? false : async.loading;
    bool get notLoaded => async == null ? false : async.notLoaded;
    String get error => async == null ? "**INTERNAL ERROR: NO PARENT**" : async.error;

    void reload() {
        async.reload();
    }
}
