library event_util;

import 'dart:async';

/**
 * Provides a stream.  This is used as a wrapper around a [StreamController]
 * because its `stream` getter returns a new object on each call.
 *
 * This allows an AngularDart component to take a stream as an argument,
 * without the problem of the StreamController constantly returning a different
 * value.
 */
abstract class StreamProvider<T> {
    Stream<T> get stream;
}



class StreamControllerStreamProvider<T> implements StreamProvider<T> {
    StreamController<T> _controller;

    @override
    Stream<T> get stream => _controller.stream;

    StreamControllerStreamProvider(this._controller);
}
