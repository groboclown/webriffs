
library single_request;

import 'dart:async';
import '../service/server.dart';

/**
 * This handles the situations where a library needs to handle
 * single requests to the server, whose results will be overwritten
 * by subsequent requests.  This has the implications that only the
 * most recent request needs to be handled, and requests-in-motion
 * can be canceled if they take too long.
 *
 * Note that Angular Http requests take the form of promises, and right
 * now they are not allowed to be cancelled.
 *
 * These are not injectable, because there should be one instance per execution
 * type.
 *
 * Requests can be put on a timer to indicate that they do not take effect until
 * that time.  If there are previous requests that occur _after_ the
 * new request, they will not be honored.  *Only the last request will be
 * honored; all others are thrown away regardless of the delay value.*
 */
class SingleRequest {
    final ServerStatusService _server;
    RequestData _head = null;
    StreamController<RequestData> _stream;
    Timer _pending = null;
    Future _active = null;

    SingleRequest(this._server) {
        _stream = new StreamController<RequestData>();
        _stream.stream.listen((RequestData request) {
                _checkRun(request);
            });
    }


    bool get hasPendingRequest => _head != null;
    bool get isActive => _active != null;


    Future<ServerResponse> add(MakeSingleRequest request,
            [ Duration delay = null ]) {
        RequestData data = new RequestData(request, delay);

        // Start the process.  We call into the stream to make sure we
        // queue up the ordering correctly.
        _head = data;
        _stream.add(data);

        return data.future;
    }


    /**
     * The core logic behind the execution of events.  This should only be
     * called by the stream listener.  Everything else needs to add pending
     * events into the stream.
     */
    void _checkRun(RequestData request) {
        // Any pending timer needs to be canceled.  We're handling either an
        // old request or a new one, and either way the currently pending
        // timers are not needed, because the last one wins.
        if (_pending != null && _pending.isActive) {
            _pending.cancel();
        }
        _pending = null;


        if (request != _head) {
            // This request is no longer valid.  Ignore it.
            return;
        }

        if (isActive) {
            _active.then((_) {
                _stream.add(request);
            }, onError: (Exception e) {
                _stream.add(request);
                throw e;
            }).catchError((Exception e) {
                _stream.add(request);
                throw e;
            });
        }


        Duration next = request.getTimeToCompletion();
        if (next != null) {
            // Put the pending request into a timer.
            _pending = new Timer(next, () { _stream.add(request); });
        }


        // Else, it's ready to run, so run it right now.

        // The current _head is no longer pending, because we're running it.
        _head = null;

        //
        request.execute(_server).then((ServerResponse resp) {
            _handleRunEnd(request);
            return resp;
        }, onError: (Exception e) {
            _handleRunEnd(request);
            throw e;
        }).catchError((Exception e) {
            _handleRunEnd(request);
            throw e;
        });
    }


    void _handleRunEnd(RequestData request) {
        // This current activity just finished, so mark us as not having
        // anything active.
        _active = null;

        // If this request is the current head, then that means we just
        // finished running the current head, and there's nothing pending.
        if (_head == request) {
            _head = null;
        }
        // Else, there's something pending.  That *should* be handled by
        // the .then handler on our _active that just completed.  Therefore,
        // do *not* repost to the stream the _head request.
    }
}




typedef Future<ServerResponse> MakeSingleRequest(ServerStatusService server);


class RequestData {
    final DateTime when;
    final Completer<ServerResponse> what;
    final MakeSingleRequest action;

    Future<ServerResponse> get future => what.future;


    RequestData(this.action, [ Duration delay = null ]) :
        when = delay == null ? null : new DateTime.now().add(delay),
        what = new Completer<ServerResponse>();

    /**
     * @return null if the request is ready, or a Duration that describes when
     *      the request will be ready next.
     */
    Duration getTimeToCompletion() {
        if (when == null) {
            return null;
        }
        var now = new DateTime.now();
        if (when.isAfter(now)) {
            return when.difference(now);
        }
        return null;
    }


    Future<ServerResponse> execute(ServerStatusService server) {
        return action(server).
            then((ServerResponse r) {
                what.complete(r);
            },
            onError: (Exception e) {
                what.completeError(e);
            }).
            catchError((Exception e) {
                what.completeError(e);
            });
    }

    /*
    @override
    int compareTo(RequestData other) {
        if (when == null) {
            if (other.when == null) {
                return 0;
            }
            return -1;
        }
        if (other.when == null) {
            return 1;
        }
        return when.compareTo(other.when);
    }
    */
}
