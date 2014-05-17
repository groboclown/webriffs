
library error_component;


import 'package:angular/angular.dart';

import '../service/error.dart';

/**
 * The UI component view of the error service information.
 */
@Component(
    selector: 'error-status',
    templateUrl: 'packages/webriffs_client/component/errorstatus_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class ErrorComponent {
    ErrorService _error;

    ErrorComponent(this._error);

    bool get canConnectToServer => _error.canConnectToServer;

    String get criticalError => _error.criticalError;

    List<ServerResponse> get notices => _error.notices;

    bool get isLoading => _error.isLoading;
}

