
library error_component;


import 'package:angular/angular.dart';

import '../service/error.dart';

/**
 * The UI component view of the error service information.
 */
@Component(
    selector: 'error-report',
    templateUrl: 'packages/webriffs_client/component/error_component.html',
    cssUrl: 'packages/webriffs_client/component/error_component.css',
    publishAs: 'cmp')
class ErrorComponent {
    ErrorService _errors;

    @NgOneWay('critical-message')
    String get criticalMessage => _errors.criticalError;

    @NgTwoWay('notices')
    List<String> get notices => _errors.notices;

    @NgOneWay('has-criticals')
    bool get hasCriticalMessages => _errors.criticalError != null;

    ErrorComponent(this._errors);
}

