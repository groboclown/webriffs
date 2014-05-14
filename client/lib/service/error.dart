
library error_service;

import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

/**
 * Shared data across controllers and services that stores the error
 * information.  This error information should only for the global data.
 */
@Injectable()
class ErrorService {
    final Logger _log = new Logger('service.ErrorService');

    /**
     * There can only be one critical error at a time.  Such a critical
     * error requires user interaction before it is cleared.
     */
    String criticalError;

    /**
     * Informative messages that the user should know about, but doesn't need
     * to act upon.
     */
    List<String> notices;

    /**
     * Are there server connection problems?
     */
    bool canConnectToServer;

    /**
     * Called when there was an exception generated during the HttpRequest.
     */
    void addHttpRequestException(Exception e) {
        //criticalError = e.toString();
        _log.severe("HttpRequest generated an error", e);
    }


    /**
     * Called when there was an error status (not 200-299) on an HTTP request
     * to the server.
     */
    void addHttpRequestError(HttpResponse e) {
        // FIXME parse the error data.
        var resp = new ErrorResponse.fromHttp(e);

    }
}


class ErrorResponse {
    final int code;
    final String message;
    final Map<String, String> problems;

    ErrorResponse._(this.code, this.message, this.problems);

    factory ErrorResponse.fromHttp(HttpResponse e) {
        String message;
        Map<String, String> problems = new Map();
        if (e.headers('Content-Type') == 'application/json') {
            Map<String, dynamic> data = JSON.decode(e.data);
            if (data['message'] != null && data['message'] is String) {
                message = data['message'];
            }
            if (data['problems'] != null && data['problems'] is Map) {
                data['problems'].forEach((k, v) {
                    if (k is String && v is String) {
                        problems[k] = v;
                    }
                });
            }
        } else {
            message = e.data.toString();
        }

        return new ErrorResponse._(e.status, message, problems);
    }


}

