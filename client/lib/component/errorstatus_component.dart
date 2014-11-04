
library error_component;


import 'package:angular/angular.dart';

import '../service/server.dart';

/**
 * The UI component view of the error service information.
 */
@Component(
    selector: 'error-status',
    useShadowDom: false,
    templateUrl: 'packages/webriffs_client/component/errorstatus_component.html'
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css'
    )
class ErrorComponent {
    ServerStatusService _error;

    ErrorComponent(this._error);

    bool get canConnectToServer => _error.canConnectToServer;

    String get criticalError => _error.criticalError;

    List<ServerResponse> get notices => _error.notices;

    bool get isLoading => _error.isLoading;

    bool showNotices = false;

    bool get hasNotices => notices != null && notices.isNotEmpty;

    bool get hasError => criticalError != null;

    bool get hasProblem => hasNotices || hasError;

    void toggleShowNotices() {
        showNotices = ! showNotices;
    }

    void clearNotices() {
        _error.criticalError = null;
        _error.notices.clear();
    }
}

