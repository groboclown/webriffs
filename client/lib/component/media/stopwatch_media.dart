library stopwatch_media;

import 'dart:async';

import 'package:angular/angular.dart';
//import 'package:logging/logging.dart';
import 'package:videoplay/api.dart';

import '../../util/time_format.dart';


/**
 * Exposes the service to the wrapping component.  It's also used in the
 * component as the state and the controller.
 */
class StopwatchMedia implements VideoPlayer {
    Stopwatch _stopwatch = new Stopwatch();
    VideoPlayerStatus _status = VideoPlayerStatus.NOT_STARTED;
    int _baseTimeMillis = 0;

    TimeDialation _currentTimeDialation = TimeDialation.NATIVE;
    TimeDialation get timeDialation => _currentTimeDialation;

    StreamController<VideoPlayerEvent> _events =
            new StreamController<VideoPlayerEvent>.broadcast();

    set timeDialation(TimeDialation td) {
        if (td != null && td != _currentTimeDialation) {
            _currentTimeDialation = td;
        }
    }

    @override
    bool get hasVideo => true;

    /**
     * What time the current video is showing.  It may return `null` if the
     * video hasn't started, or has ended.
     */
    @override
    Duration get playbackTime =>
            new Duration(milliseconds: _baseTimeMillis +
                _stopwatch.elapsedMilliseconds);

    /**
     *
     */
    @override
    VideoPlayerStatus get status => _status;

    /**
     * Returns a value between 0 and 100 as a rough percentage of the
     * video that has already been downloaded.
     */
    @override
    double get percentVideoLoaded => 100.0;

    /**
     * How long the video is.
     */
    @override
    Duration get videoDuration => new Duration(milliseconds: 0);

    /**
     * The text of the current error message, or `null` if there is no current
     * error.
     */
    @override
    String get errorText => null;

    /**
     * `0` if there is no error, otherwise a video player dependent error
     * message code.
     */
    @override
    int get errorCode => 0;

    /**
     * Event stream for changes to the video player status.
     */
    @override
    Stream<VideoPlayerEvent> get statusChangeEvents => _events.stream;

    @override
    String get videoId => "";

    @override
    void loadVideo(String videoId) {
        // do nothing
    }

    @override
    void play() {
        _stopwatch.start();
        _status = VideoPlayerStatus.PLAYING;
        _events.add(new VideoPlayerEvent(this, new DateTime.now(), _status));
    }

    @override
    void pause() {
        _stopwatch.stop();
        _status = VideoPlayerStatus.PAUSED;
        _events.add(new VideoPlayerEvent(this, new DateTime.now(), _status));
    }

    @override
    void stop() {
        _stopwatch.stop();
        _stopwatch.reset();
        _baseTimeMillis = 0;
        _status = VideoPlayerStatus.NOT_STARTED;
        _events.add(new VideoPlayerEvent(this, new DateTime.now(), _status));
    }

    @override
    void seekTo(Duration time) {
        // do nothing
    }

    /**
     * Destroy the player in the web page.
     */
    @override
    void destroy() {
        _events.close();
        // do nothing
    }


    void setTime(int millis) {
        if (millis < 0 || millis == null) {
            throw new Exception("bad time: ${millis}");
        }
        _baseTimeMillis = millis;
        _stopwatch.reset();
    }
}


