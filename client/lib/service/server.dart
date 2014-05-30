
library error_service;

import 'dart:convert';
import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';


typedef bool IsErrorCheckerFunc(int statusCode);

final IsErrorCheckerFunc DEFAULT_IS_ERROR_CHECKER_FUNC = (int statusCode) {
    return (statusCode < 200 || statusCode >= 300);
};

/**
 * Shared data across controllers and services that stores the error
 * information.  This error information should only for the global data.
 */
@Injectable()
class ServerStatusService {
    static final Logger _log = new Logger('service.ServerStatusService');
    static final Map<String, dynamic> _headers = {
      'Content-Type': 'application/json'
    };

    final Http _http;

    bool _canConnectToServer;

    int _activeRequests = 0;

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
    bool get canConnectToServer => _canConnectToServer;

    int get activeRequests => _activeRequests;

    bool get isLoading => _activeRequests > 0;

    ServerStatusService(this._http);

    Future<ServerResponse> get(String url, String csrfToken,
            { IsErrorCheckerFunc isErrorChecker : null }) {
        String fullUrl = _fullUrl(url);
        try {
            _activeRequests++;
            Map<String, dynamic> headers = new Map.from(_headers);
            if (csrfToken != null) {
                headers['csrf-token'] = csrfToken;
            }
            return _http.get(fullUrl, headers: headers)
                .then((HttpResponse response) {
                    _activeRequests--;
                    return _processResponse("GET", fullUrl, null, response,
                        isErrorChecker);
                }, onError: (HttpResponse response) {
                    _activeRequests--;
                    return _processResponse("GET", fullUrl, null, response,
                        isErrorChecker);
                }).catchError((Exception e) {
                    _activeRequests--;
                    return _addHttpRequestException("GET", fullUrl, null, e);
                });
        } catch (e) {
            _activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> put(String url, String csrfToken,
                               { Map<String, dynamic> data : null,
                               IsErrorCheckerFunc isErrorChecker : null }) {
        String jsonData;
        if (data == null) {
            jsonData = "{}";
        } else {
            jsonData = JSON.encode(data);
        }
        String fullUrl = _fullUrl(url);
        try {
            _activeRequests++;
            Map<String, dynamic> headers = new Map.from(_headers);
            if (csrfToken != null) {
                headers['csrf-token'] = csrfToken;
            }
            return _http.put(fullUrl, jsonData, headers: headers).then((HttpResponse response) {
                _activeRequests--;
                return _processResponse("PUT", fullUrl, jsonData, response, isErrorChecker);
            }, onError: (HttpResponse response) {
                _activeRequests--;
                return _processResponse("PUT", fullUrl, jsonData, response, isErrorChecker);
            }).catchError((Exception e) {
                _activeRequests--;
                return _addHttpRequestException("PUT", fullUrl, jsonData, e);
            });
        } catch (e) {
            _activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> post(String url, String csrfToken,
                                { Map<String, dynamic> data : null,
                                IsErrorCheckerFunc isErrorChecker: null }) {
        String jsonData;
        if (data == null) {
            jsonData = "{}";
        } else {
            jsonData = JSON.encode(data);
        }
        String fullUrl = _fullUrl(url);
        try {
            _activeRequests++;
            Map<String, dynamic> headers = new Map.from(_headers);
            if (csrfToken != null) {
                headers['csrf-token'] = csrfToken;
            }
            return _http.post(fullUrl, jsonData, headers: headers)
            .then((HttpResponse response) {
                _activeRequests--;
                return _processResponse("POST", fullUrl, jsonData, response,
                    isErrorChecker);
            }, onError: (HttpResponse response) {
                _activeRequests--;
                return _processResponse("POST", fullUrl, jsonData, response,
                    isErrorChecker);
            }).catchError((Exception e) {
                _activeRequests--;
                return _addHttpRequestException("POST", fullUrl, jsonData, e);
            });
        } catch (e) {
            _activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> delete(String url,  String csrfToken,
            { IsErrorCheckerFunc isErrorChecker : null }) {
        String fullUrl = _fullUrl(url);
        try {
            _activeRequests++;
            Map<String, dynamic> headers = new Map.from(_headers);
            if (csrfToken != null) {
                headers['csrf-token'] = csrfToken;
            }
            return _http.delete(fullUrl, headers: headers)
            .then((HttpResponse response) {
                _activeRequests--;
                return _processResponse("DELETE", fullUrl, null, response,
                    isErrorChecker);
            }, onError: (HttpResponse response) {
                _activeRequests--;
                return _processResponse("DELETE", fullUrl, null, response,
                    isErrorChecker);
            }).catchError((Exception e) {
                _activeRequests--;
                return _addHttpRequestException("DELETE", fullUrl, null, e);
            });
        } catch (e) {
            _activeRequests--;
            throw e;
        }
    }


    String _fullUrl(String url) {
        if (! url.startsWith('/')) {
            url = '/' + url;
        }
        return 'api' + url;
    }



    /**
     * Called when there was an exception generated during the HttpRequest.
     */
    ServerResponse _addHttpRequestException(String method, String url,
            String data, Exception e) {
        // TODO clean up the error some?
        criticalError = e.toString();
        _log.severe("HttpRequest ${method} to [${url}] " +
            (data == null ? "" : " <= [${data}]") + " generated an error", e);
        _canConnectToServer = false;
        return new ServerResponse(null, null);
    }


    /**
     * Called after the response comes back from the server.
     */
    ServerResponse _processResponse(String method, String url,
                                   String data, HttpResponse e,
                                   IsErrorCheckerFunc isErrorChecker) {
        if (e != null) {
            _canConnectToServer = true;
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


