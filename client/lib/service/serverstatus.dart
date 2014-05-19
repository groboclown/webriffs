
library error_service;

import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';


typedef bool IsErrorCheckerFunc(int statusCode);

final IsErrorCheckerFunc DEFAULT_IS_ERROR_CHECKER_FUNC = (int statusCode) {
    return (statusCode < 200 || statusCode >= 300);
};

/**
 * Shared data across controllers and services that stores the error
 * information.  This error information should only for the global data.
 *
 * TODO this needs to be a more general ServerStatusService.
 */
@Injectable()
class ServerStatusService {
    static final Logger _log = new Logger('service.ServerStatusService');

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


    int activeRequests = 0;

    bool get isLoading => activeRequests > 0;


    /**
     * Called when there was an exception generated during the HttpRequest.
     */
    ServerResponse addHttpRequestException(String method, String url,
            String data, Exception e) {
        // TODO clean up the error some?
        criticalError = e.toString();
        _log.severe("HttpRequest ${method} to [${url}] " +
            (data == null ? "" : " <= [${data}]") + " generated an error", e);
        canConnectToServer = false;
        return new ServerResponse(null, null);
    }


    /**
     * Called after the response comes back from the server.
     */
    ServerResponse processResponse(String method, String url,
                                   String data, HttpResponse e,
                                   IsErrorCheckerFunc isErrorChecker) {
        if (e != null) {
            canConnectToServer = true;
            ServerResponse resp = new ServerResponse(e, isErrorChecker);
            if (resp.wasError) {
                notices.add(resp);
            }
            _log.finer("${method} [${url}]: ${e.status}" +
                (data == null ? "" : " => [${data}]") + " <= [${e.data}]");
            _log.finest("  response headers (" + e.headers().runtimeType.toString() + "): " + e.headers().toString());
            _log.finest("  response content type: " + e.headers('content-type').toString());
            _log.finest("  response json data: " + resp.jsonData.toString());
            return resp;
        }
        _log.finer("${method} [${url}]: ?" +
            (data == null ? "" : " => ${data}") + " <= !!no result!!");
        return new ServerResponse(null, null);
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


    factory ServerResponse(HttpResponse http,
                           IsErrorCheckerFunc isErrorChecker) {
        if (isErrorChecker == null) {
            isErrorChecker = DEFAULT_IS_ERROR_CHECKER_FUNC;
        }
        if (http == null) {
            return new ServerResponse._(
                null, true, "", new Map(), new Map(), 501);
        }
        var wasError = isErrorChecker(http.status);
        var jsonData = null;

        if (http.data != null && (
                http.headers('content-type') == 'application/json' ||
                http.headers('content-type') == 'text/json')) {
            if (http.data is String) {
                // the Http code can automatically decode Json responses.
                jsonData = JSON.decode(http.data);
            } else if (http.data is Map) {
                jsonData = http.data;
            }
            // else no data
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


