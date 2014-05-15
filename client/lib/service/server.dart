
library server_service;

import 'dart:async';
import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import 'error.dart';


/**
 * Parent Service class that handles the communication with the
 * server via the RESTful Json API.
 */
class AbstractServerService {
    final Http _http;
    final ErrorService _error;
    final Map<String, dynamic> _headers;


    AbstractServerService(this._http, this._error) :
            _headers = {} {
        _headers['Content-Type'] = 'application/json';
    }

    Future<ServerResponse> get(String url) {
        String fullUrl = _fullUrl(url);
        return _http.get(fullUrl, headers: _headers)
            .then((HttpResponse response) {
                return _error.processResponse("GET", fullUrl, null, response);
            }, onError: (HttpResponse response) {
                return _error.processResponse("GET", fullUrl, null, response);
            }).catchError((Exception e) {
                return _error.addHttpRequestException("GET", fullUrl, null, e);
            });
    }

    Future<ServerResponse> put(String url,
                               [ Map<String, dynamic> data = null ]) {
        String jsonData;
        if (data == null) {
            jsonData = "{}";
        } else {
            jsonData = JSON.encode(data);
        }
        String fullUrl = _fullUrl(url);
        return _http.put(fullUrl, jsonData, headers: _headers)
        .then((HttpResponse response) {
            return _error.processResponse("PUT", fullUrl, jsonData, response);
        }, onError: (HttpResponse response) {
            return _error.processResponse("PUT", fullUrl, jsonData, response);
        }).catchError((Exception e) {
            return _error.addHttpRequestException("PUT", fullUrl, jsonData, e);
        });
    }

    Future<ServerResponse> post(String url,
                                [ Map<String, dynamic> data = null ]) {
        String jsonData;
        if (data == null) {
            jsonData = "{}";
        } else {
            jsonData = JSON.encode(data);
        }
        String fullUrl = _fullUrl(url);
        return _http.post(fullUrl, jsonData, headers: _headers)
        .then((HttpResponse response) {
            return _error.processResponse("POST", fullUrl, jsonData, response);
        }, onError: (HttpResponse response) {
            return _error.processResponse("POST", fullUrl, jsonData, response);
        }).catchError((Exception e) {
            return _error.addHttpRequestException("POST", fullUrl, jsonData, e);
        });
    }

    Future<ServerResponse> delete(String url) {
        String fullUrl = _fullUrl(url);
        return _http.delete(fullUrl, headers: _headers)
        .then((HttpResponse response) {
            return _error.processResponse("DELETE", fullUrl, null, response);
        }, onError: (HttpResponse response) {
            return _error.processResponse("DELETE", fullUrl, null, response);
        }).catchError((Exception e) {
            return _error.addHttpRequestException("DELETE", fullUrl, null, e);
        });
    }


    String _fullUrl(String url) {
        if (! url.startsWith('/')) {
            url = '/' + url;
        }
        return 'api' + url;
    }
}
