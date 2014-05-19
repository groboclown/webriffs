
library server_service;

import 'dart:async';
import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import 'serverstatus.dart';


/**
 * Parent Service class that handles the communication with the
 * server via the RESTful Json API.
 */
class AbstractServerService {
    final Http _http;
    final ServerStatusService _status;
    final Map<String, dynamic> _headers;


    bool get isLoading => _status.isLoading;


    AbstractServerService(this._http, this._status) :
            _headers = {} {
        _headers['Content-Type'] = 'application/json';
    }

    Future<ServerResponse> get(String url, {
            IsErrorCheckerFunc isErrorChecker : null }) {
        // TODO this isn't the right approach to capturing the requests in progress.
        String fullUrl = _fullUrl(url);
        try {
            _status.activeRequests++;
            return _http.get(fullUrl, headers: _headers)
                .then((HttpResponse response) {
                    _status.activeRequests--;
                    return _status.processResponse("GET", fullUrl, null, response,
                        isErrorChecker);
                }, onError: (HttpResponse response) {
                    _status.activeRequests--;
                    return _status.processResponse("GET", fullUrl, null, response,
                        isErrorChecker);
                }).catchError((Exception e) {
                    _status.activeRequests--;
                    return _status.addHttpRequestException("GET", fullUrl, null, e);
                });
        } catch (e) {
            _status.activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> put(String url,
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
            _status.activeRequests++;
            return _http.put(fullUrl, jsonData, headers: _headers).then((HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("PUT", fullUrl, jsonData, response, isErrorChecker);
            }, onError: (HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("PUT", fullUrl, jsonData, response, isErrorChecker);
            }).catchError((Exception e) {
                _status.activeRequests--;
                return _status.addHttpRequestException("PUT", fullUrl, jsonData, e);
            });
        } catch (e) {
            _status.activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> post(String url,
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
            _status.activeRequests++;
            return _http.post(fullUrl, jsonData, headers: _headers)
            .then((HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("POST", fullUrl, jsonData, response,
                    isErrorChecker);
            }, onError: (HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("POST", fullUrl, jsonData, response,
                    isErrorChecker);
            }).catchError((Exception e) {
                _status.activeRequests--;
                return _status.addHttpRequestException("POST", fullUrl, jsonData, e);
            });
        } catch (e) {
            _status.activeRequests--;
            throw e;
        }
    }

    Future<ServerResponse> delete(String url, {
            IsErrorCheckerFunc isErrorChecker : null }) {
        String fullUrl = _fullUrl(url);
        try {
            _status.activeRequests++;
            return _http.delete(fullUrl, headers: _headers)
            .then((HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("DELETE", fullUrl, null, response,
                    isErrorChecker);
            }, onError: (HttpResponse response) {
                _status.activeRequests--;
                return _status.processResponse("DELETE", fullUrl, null, response,
                    isErrorChecker);
            }).catchError((Exception e) {
                _status.activeRequests--;
                return _status.addHttpRequestException("DELETE", fullUrl, null, e);
            });
        } catch (e) {
            _status.activeRequests--;
            throw e;
        }
    }


    String _fullUrl(String url) {
        if (! url.startsWith('/')) {
            url = '/' + url;
        }
        return 'api' + url;
    }
}
