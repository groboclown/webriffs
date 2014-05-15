library errorstatus_model;


import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import 'service/error.dart';

/**
 * Currently, there doesn't seem to be a way to create the
 * model without a controller.  It might be done by registering
 * the model at the top level, but that seems like overkill.
 */
@Controller(
    selector: '[error-status]',
    publishAs: 'ctrl'
)
class ErrorStatusController {
    ErrorService service;

    ErrorStatusController(this.service);
}
