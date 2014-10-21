
library error_component;


import 'package:angular/angular.dart';

import '../service/server.dart';

/**
 * The UI component view of the error service information.
 */
@Component(
    selector: 'error-status',
    templateUrl: 'errorstatus_component.html'
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css'
    )
class ErrorComponent {
    ServerStatusService _error;

    ErrorComponent(this._error);

    bool get canConnectToServer => _error.canConnectToServer;

    String get criticalError => _error.criticalError;

    List<ServerResponse> get notices => _error.notices;

    bool get isLoading => _error.isLoading;
}

