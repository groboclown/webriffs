library asyncstatus_component;


import 'dart:html';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../util/async_component.dart';

@Component(
    selector: 'async-status',
    templateUrl: 'packages/webriffs_client/component/asyncstatus_component.html',
    publishAs: 'cmp')
class AsyncStatusComponent {
    @NgOneWay('parent')
    AsyncComponent async;

    bool get loadedError => async.loadedError;
    bool get loadedSuccessful => async.loadedSuccessful;
    bool get loading => async.loading;
    bool get notLoaded => async.notLoaded;
    String get error => async.error;
}