
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
    NgModel _ngModel;

    ErrorService get value => _ngModel.modelValue;

    ErrorComponent(this._ngModel, ErrorService error) {
        // FIXME HAAAAAAAAAAACK
        if (_ngModel == null) {
            throw new Exception("null ngModel");
        }
        if (_ngModel.modelValue != null) {
            if (!(_ngModel.modelValue is ErrorService)) {
                throw new Exception("model value is not ErrorService");
            }
        } else {
            _ngModel.modelValue = error;
        }
    }
}

