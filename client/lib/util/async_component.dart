
library async_component;

import 'dart:async';

import '../service/server.dart';
import 'paging.dart';
import 'single_request.dart';


/**
 * Standard interface for components to give a universal loading state and
 * error state notification to the UI.
 */
abstract class AsyncComponent {
    bool get loadedError;
    bool get loadedSuccessful;
    bool get loading;
    bool get notLoaded;
    String get error;

    void reload();
}






typedef Future<ServerResponse> MakeCsrfRequest(
        ServerStatusService server, String token);



/**
 * Handles simple requests to the server.  The component should invoke
 * something like:
 *
 *      void onClick() {
 *          csrfRequest(_server, 'create-item',
 *              (ServerStatusService server, String token) =>
 *                  server.put(path, data, token));
 *      }
 *
 * And then implement the data handling in the `onSuccess` method.
 *
 */
abstract class RequestHandlingComponent implements AsyncComponent {
    bool _hasError = false;
    String _errorMessage = null;
    bool _loaded = false;
    bool _loading = false;

    @override
    bool get loadedError => _hasError;

    @override
    bool get loadedSuccessful => ! _hasError && _loaded;

    @override
    bool get loading => ! _hasError && _loading;

    @override
    bool get notLoaded => ! _loaded && ! _loading && ! _hasError;

    @override
    String get error => _errorMessage;


    Future<ServerResponse> csrfRequest(ServerStatusService server,
            String action, MakeCsrfRequest req) {
        // For the CSRF token request, we want to indicate as soon as
        // possible to the user that, indeed, their click request to
        // load data was registered.  If we don't update these variables
        // until the CSRF token is received, we leave the user thinking
        // that the UI didn't "hear" the click, and may try to click again.
        _loaded = false;
        _loading = true;
        _hasError = false;
        _errorMessage = null;

        return server.createCsrfToken(action).then((String token) {
            return handleRequest(req(server, token));
        }, onError: (Exception e) {
            onError(e);
        }).catchError((Exception e) {
            onError(e);
        });
    }


    Future<ServerResponse> handleRequest(Future<ServerResponse> response) {
        _loaded = false;
        _loading = true;
        _hasError = false;
        _errorMessage = null;
        return response.then((ServerResponse resp) {
            if (resp.wasError) {
                _hasError = true;
                _errorMessage = resp.message;
                _loaded = false;
                _loading = false;
                return resp;
            } else {
                _hasError = false;
                _errorMessage = null;
                Future<ServerResponse> ret = onSuccess(resp);
                if (ret == null) {
                    _loaded = true;
                    _loading = false;
                    return resp;
                } else {
                    return ret.then((ServerResponse r) {
                        _loaded = true;
                        _loading = false;
                        return r;
                    }, onError: (Exception e) {
                        onError(e);
                    }).catchError((Exception e) {
                        onError(e);
                    });
                }
            }
        }, onError: (Exception e) {
            onError(e);
        }).catchError((Exception e) {
            onError(e);
        });
    }


    Future<ServerResponse> onSuccess(ServerResponse resp);


    void onError(Exception e) {
        _hasError = true;
        _errorMessage = e.toString();
        _loaded = false;
        _loading = false;
    }

}


class ResponseException implements Exception {
    final ServerResponse response;
    final Exception error;
    final String message;
    final Map<String, String> parameters;

    factory ResponseException.server(ServerResponse response) {
        return new ResponseException._(
                response, null, response.message, response.parameterNotes);
    }

    factory ResponseException.exception(Exception exception) {
        return new ResponseException._(
                null, exception, exception.toString(),
                new Map<String, String>());
    }

    ResponseException._(this.response, this.error, this.message,
            this.parameters);

    @override
    String toString() {
        String ret = message;
        if (parameters != null && parameters.isNotEmpty) {
            ret += " [";
            parameters.keys.reduce((String full, String next) =>
                    full == null
                    ? (next + ": " + parameters[next])
                    : ("; " + next + ": " + parameters[next]));
            ret += " ]";
        }
        return ret;
    }
}


/**
 * Handles single, interruptable requests to the server.
 *
 * The internal error handling and loading status is specially crafted
 * to be set _around_ the returned [Future] objects.  This allows calls into
 * [#getFor] and [#addRequestFor] to chain [Future] calls make subsequent
 * requests to the server.
 */
abstract class BasicSingleRequestComponent implements AsyncComponent {
    final SingleRequest _server;
    bool _hasError = false;
    String _errorMessage = null;
    bool _loaded = false;
    bool _loading = false;

    @override
    bool get loadedError => _hasError;

    @override
    bool get loadedSuccessful => ! _hasError && _loaded;

    @override
    bool get loading => ! _hasError && _loading;

    @override
    bool get notLoaded => ! _loaded && ! _loading && ! _hasError;

    @override
    String get error => _errorMessage;


    BasicSingleRequestComponent(ServerStatusService server) :
        _server = new SingleRequest(server);


    Future<ServerResponse> getWithToken(String url, String action) {
        return addRequestFor((ServerStatusService server) {
            return server.createCsrfToken(action).then((String token) =>
                    server.get(url,  token));
        });
    }


    Future<ServerResponse> getFor(String url, [ String csrfToken ]) {
        return addRequestFor((ServerStatusService server) {
            return server.get(url, csrfToken);
        });
    }


    Future<ServerResponse> addRequestWithToken(MakeCsrfRequest request,
            String action) {
        return addRequestFor((ServerStatusService server) {
            return server.createCsrfToken(action).then((String token) =>
                    request(server, token));
        });
    }


    /**
     * The Future returned by this method will run once the requested
     * service returns data.  Only after all of the returned Future code
     * completes does this component mark itself as being finished.
     */
    Future<ServerResponse> addRequestFor(MakeSingleRequest request,
            [ Duration delay ]) {
        // We don't start loading the data until the request is run.

        // The code should only mark this response as being completed when
        // the returned future completes.  We do that by returning one
        // Future that is wrapped up with other Futures.

        // We return a Completer that begins once the server response returns.
        Completer<ServerResponse> returned = new Completer<ServerResponse>();

        Future<ServerResponse> service = _server
            .add((ServerStatusService server) {
                    _loaded = false;
                    _loading = true;
                    _hasError = false;
                    _errorMessage = null;
                    return request(server);
                }, delay)
            .then((ServerResponse resp) {
                if (resp.wasError) {
                    var err = new ResponseException.server(resp);
                    returned.completeError(err);
                    throw err;
                }
                _hasError = false;
                _errorMessage = null;
                returned.complete(resp);
            }, onError: (Exception e) {
                var err = new ResponseException.exception(e);
                returned.completeError(err);
                throw err;
            });

        Future complete = Future.wait([ service, returned.future ])
            .then((_) {
                _loaded = true;
                _loading = false;
                _hasError = false;
                _errorMessage = null;
            })
            .catchError((Exception e) {
                _loaded = false;
                _loading = false;
                _hasError = true;
                _errorMessage = e.toString();
            });

        return returned.future;
    }
}


/**
 * A [BasicSingleRequestComponent] that only deals with one kind of request.
 * The requests are handled by the overridden methods
 * [#onSuccess] and [#onError].
 */
abstract class SingleRequestComponent extends BasicSingleRequestComponent {
    SingleRequestComponent(ServerStatusService server) :
        super(server);

    Future<ServerResponse> onSuccess(ServerResponse response);

    Future<ServerResponse> get(String url, [ String csrfToken = null ]) {
        return getFor(url, csrfToken)
            .then((ServerResponse response) => onSuccess(response))
            .catchError((Exception e) {
                onError(e);
            });
    }

    Future<ServerResponse> addRequest(MakeSingleRequest request,
            [ Duration delay ]) {
        return addRequestFor(request, delay)
            .then((ServerResponse response) => onSuccess(response))
            .catchError((Exception e) {
                onError(e);
            });
    }

    void onError(Exception e) {
        // do nothing
    }
}



/**
 * Standard component parent class that correctly handles the loading and
 * error states.  It provides handy methods that can be called by the
 * UI to change the page state immediately.
 *
 * Do not directly request an update in the `current` class (use it just
 * for getting the current data page state).  Instead, use the methods
 * in this class, so that the loading and error state values are correctly
 * updated.
 */
abstract class PagingComponent implements AsyncComponent {
    final ServerStatusService _server;
    PageState _current;

    PageState get current => _current;
    ServerStatusService get server => _server;

    bool _loading = false;
    bool _localError = false;
    String _localErrorMessage = null;

    @override
    bool get loadedError => _current.hasError || _localError;

    @override
    bool get loadedSuccessful => ! loadedError &&
            _current.loadedFromServer && ! _loading;

    @override
    bool get loading => ! loadedError && _loading;

    @override
    bool get notLoaded => ! _current.loadedFromServer && ! _loading &&
            ! loadedError;

    @override
    String get error =>
        (_current.hasError ? _current.errorMessage : _localErrorMessage);

    bool get isDescSort => _current.isDescSort;
    bool get isAscSort => ! _current.isDescSort;


    PagingComponent(this._server, String path, { Duration delay : null,
            bool singleRequest : true, String csrfTokenId : null }) {
        _current = new PageState(server, path,
            (PageState pageState, Iterable<dynamic> data, ServerResponse response) {
                Future<ServerResponse> ret;
                if (response.wasError) {
                    ret = onError(response);
                } else {
                    ret = onSuccess(data);
                }
                if (ret == null) {
                    _loading = false;
                    return response;
                }
                return ret.then((ServerResponse resp) { _loading = false; },
                        onError: (Exception e) {
                            _localError = true;
                            _localErrorMessage = e.toString();
                            _loading = false;
                        }).catchError((Exception e) {
                            _localError = true;
                            _localErrorMessage = e.toString();
                            _loading = false;
                        });
            }, delay: delay, singleRequest: singleRequest,
            csrfTokenId: csrfTokenId);
    }


    void setSortBy(String columnName, [ String newSortOrder = 'A' ]) {
        update(newSortedBy: columnName, newSortOrder: newSortOrder);
    }


    void toggleSortOrder() {
        String nso = isAscSort ? 'D' : 'A';
        update(newSortOrder: nso);
    }


    void setSortOrder(String newSortOrder) {
        update(newSortOrder: newSortOrder);
    }


    void setPage(int page) {
        update(nextPage: page);
    }


    void setRecordsPerPage(int count) {
        update(newRecordsPerPage: count);
    }


    void setFilter(String name, dynamic value) {
        Map<String, dynamic> newFilters = { name: value };
        update(newFilters: newFilters);
    }


    void setNewFilters(Map<String, dynamic> newFilters) {
        update(newFilters: newFilters);
    }


    void update({ int nextPage: null,
                int newRecordsPerPage: null, String newSortedBy: null,
                String newSortOrder: null,
                Map<String, dynamic> newFilters: null}) {
        _localError = false;
        _localErrorMessage = null;
        _loading = true;
        _current.updateFromServer(nextPage: nextPage,
                newRecordsPerPage: newRecordsPerPage,
                newSortedBy: newSortedBy,
                newSortOrder: newSortOrder,
                newFilters: newFilters);
    }


    void reload() {
        update();
    }



    /**
     * @return null if there is no further processing to perform, otherwise
     *      the chained future response.
     */
    Future<ServerResponse> onSuccess(Iterable<dynamic> data);


    /**
     * Handles the error conditions.  By default, it does nothing.
     */
    Future<ServerResponse> onError(ServerResponse response) {
        return null;
    }
}