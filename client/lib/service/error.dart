
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
    List<ServerResponse> notices = new List<ServerResponse>();

    /**
     * Are there server connection problems?
     */
    bool canConnectToServer;

    /**
     * Called when there was an exception generated during the HttpRequest.
     */
    void addHttpRequestException(Exception e) {
        // TODO clean up the error some?
        criticalError = e.toString();
        _log.severe("HttpRequest generated an error", e);
        canConnectToServer = false;
    }


    /**
     * Called when there was an error status (not 200-299) on an HTTP request
     * to the server.
     */
    ServerResponse processResponse(HttpResponse e) {
        if (e != null) {
            canConnectToServer = true;
            ServerResponse resp = new ServerResponse(e);
            if (resp.wasError) {
                notices.add(resp);
            }
            return resp;
        }
        return null;
    }
}


class ServerResponse {
    final HttpResponse http;
    final int status;
    final Map<String, dynamic> jsonData;
    final bool wasError;
    final String message;
    final Map<String, String> parameterNotes;

    ServerResponse._(this.http, this.wasError, this.message,
                     this.parameterNotes, this.jsonData, this.status);


    factory ServerResponse(HttpResponse http) {
        var wasError = (http.status < 200 || http.status >= 300);
        var jsonData = null;

        if (http.headers('Content-Type') == 'application/json' ||
                http.headers('Content-Type') == 'text/json') {
            jsonData = JSON.decode(http.data);
        }

        var message = null;
        var parameterNotes = new Map<String, String>();
        if (jsonData != null) {
            if (jsonData['message'] != null && jsonData['message'] is String) {
                message = jsonData['message'];
            }
            if (jsonData['problems'] != null && jsonData['problems'] is Map) {
                jsonData['problems'].forEach((k, v) {
                    if (k is String && v is String) {
                        parameterNotes[k] = v;
                    }
                });
            } else if (jsonData['parameters'] != null &&
                    jsonData['parameters'] is Map) {
                jsonData['parameters'].forEach((k, v) {
                    if (k is String && v is String) {
                        parameterNotes[k] = v;
                    }
                });

            }
        }

        return new ServerResponse._(http, wasError, message, parameterNotes,
            jsonData, http.status);
    }
}


