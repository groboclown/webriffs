/**
 * Sets up time-based alerts to trigger events based on the media.
 */
library media_controller;

import 'dart:async';
import 'package:videoplay/api.dart';

import '../../util/time_format.dart';

import 'stopwatch_media.dart';

typedef void OnTimedAlert(int millisecondTime);


abstract class MediaTimeProvider {
    Duration get serverTime;
    String get displayTime;
    TimeDialation get dialation;
    int convertToServerTime(String timestr);
}


class VideoPlayerTimeProvider implements MediaTimeProvider {
    final StreamController<VideoPlayer> _stream =
            new StreamController.broadcast();
    Stream<VideoPlayer> get stream => _stream.stream;

    VideoPlayer _player;

    VideoPlayer get player => _player;

    set player(VideoPlayer p) {
        _stream.add(p);
    }

    @override
    Duration get serverTime => _player == null ? null : _player.playbackTime;

    @override
    TimeDialation get dialation => _player == null
        ? TimeDialation.NATIVE
        : player is StopwatchMedia
            ? (player as StopwatchMedia).timeDialation
            : TimeDialation.NATIVE;

    @override
    String get displayTime => _player == null ? "" :
        dialation.displayString(player.playbackTime.inMilliseconds / 1000.0);

    @override
    int convertToServerTime(String timestr) => _player == null ? null :
        (dialation.parseDisplay(timestr) * 1000.0).toInt();


    void attachToAlertController(MediaAlertController controller) {
        stream.listen((VideoPlayer vp) {
            controller.stop();
            if (vp != null) {
                controller.start(vp);
            }
        });
    }
}



/**
 * Adds [MediaAlertEvent] objects to a [MediaAlertController].  It's more
 * efficient to load up all the events at once, rather than one at a time.
 */
abstract class MediaAlertRegistry {
    MediaAlertEvent add(int absoluteTimeMillis, OnTimedAlert listener);


    MediaAlertEvent addRepeat(int repeatsEvery, OnTimedAlert listener);


    void batchAdd(Iterable<MediaAlertEvent> events);


    void remove(MediaAlertEvent event);
}


/**
 * Allows control over when the internal monitor of the time provider executes.
 * This follows the media model where it's the responsibility of the wrapping
 * web page to poll the current time.
 *
 * If the time provider uses a push model, then a different controller will
 * need to be used.
 */
abstract class MediaAlertController implements MediaAlertRegistry {
    void stop();

    void start(VideoPlayer provider, [ Duration interval ]);

    static void connectPlayer(VideoPlayer player,
            MediaAlertController controller, [ Duration interval ]) {
        player.statusChangeEvents.listen((VideoPlayerEvent e) {
            if (e.status == VideoPlayerStatus.PLAYING) {
                controller.start(e.videoPlayer, interval);
            } else {
                controller.stop();
            }
        });
    }
}



/**
 * Controls the sending of alerts to listeners when specific time points in
 * the media playback occur.  Note that the playback time can jump backwards
 * and forwards, and this shouldn't signal all of those when a jump happens.
 *
 * This is designed to allow for subclasses to manage additional, external
 * lists of event types (such as Quips).
 */
class BaseMediaAlertController implements MediaAlertController {
    /** a list of events that will happen in the future, according to the
     * most recent time poll.  This should always be sorted such that the
     * next event to display is the last in the list. */
    final List<OnceMediaAlertEvent> _oneTimeFuture = [];

    /** a list of events that happened in the past, according to the
     * most recent time poll.  This should always be sorted such that the
     * most recently displayed event is last in the list. */
    final List<OnceMediaAlertEvent> _oneTimePast = [];

    /** all repeating alerts. */
    final List<RepeatMediaAlertEvent> _repeaters = [];
    Timer _timer;
    VideoPlayer _current;
    int _previousCheckedTime = 0;

    int get previousCheckedTime => _previousCheckedTime;

    @override
    MediaAlertEvent add(int absoluteTimeMillis, OnTimedAlert listener) {
        var ret = new OnceMediaAlertEvent(absoluteTimeMillis, listener);
        if (absoluteTimeMillis <= _previousCheckedTime) {
            _oneTimePast.add(ret);
            // oldest (lowest) ones last
            _oneTimePast.sort(OnceMediaAlertEventCompareReversed);
        } else {
            _oneTimeFuture.add(ret);
            // oldest (lowest) ones last
            _oneTimeFuture.sort(OnceMediaAlertEventCompareReversed);
        }
        return ret;
    }


    @override
    MediaAlertEvent addRepeat(int repeatsEvery, OnTimedAlert listener) {
        var ret = new RepeatMediaAlertEvent(repeatsEvery, listener);
        _repeaters.add(ret);
        return ret;
    }


    @override
    void batchAdd(Iterable<MediaAlertEvent> events) {
        bool sortFuture = false;
        bool sortPast = false;
        for (MediaAlertEvent e in events) {
            if (e is OnceMediaAlertEvent) {
                if (e.millisecond <= _previousCheckedTime) {
                    _oneTimePast.add(e);
                    sortPast = true;
                } else {
                    _oneTimeFuture.add(e);
                    sortFuture = true;
                }
            } else
            if (e is RepeatMediaAlertEvent) {
                // no sorting with repeaters
                _repeaters.add(e);
            }
            // Ignore others - they may be handled by the subclass.
        }

        if (sortFuture) {
            // oldest (lowest) ones last
            _oneTimeFuture.sort(OnceMediaAlertEventCompareReversed);
        }
        if (sortPast) {
            // oldest (lowest) ones last
            _oneTimePast.sort(OnceMediaAlertEventCompareReversed);
        }
    }


    @override
    void remove(MediaAlertEvent event) {
        // Note: removing an item does not change the sort order.

        // FIXME because these are sorted, we can perform a binary search
        // for the element.  If removes are done quite a bit, then an
        // alternative is to keep a hashmap of the event indicies.
        _oneTimePast.remove(event);
        _oneTimeFuture.remove(event);

        // This is unsorted.
        _repeaters.remove(event);
    }


    @override
    void start(VideoPlayer player, [ Duration interval ]) {
        if (player == null) {
            throw new Exception("null player");
        }
        if (_current != null) {
            throw new Exception("still running");
        }
        _current = player;

        if (interval == null) {
            interval = new Duration(milliseconds: 50);
        }

        // TODO experiment with different look-ahead times.
        int lookAhead = interval.inMilliseconds ~/ 3;

        _timer = new Timer.periodic(interval, (Timer timer) {
            if (_current == null) {
                _timer.cancel();
                return;
            }
            int currentTime = _current.playbackTime.inMilliseconds;
            if (currentTime == _previousCheckedTime) {
                return;
            }
            if (currentTime - _previousCheckedTime < 0) {
                handleSkipBackwardsTo(currentTime);
            }
            if (currentTime > _previousCheckedTime +
                    interval.inMilliseconds + lookAhead) {
                handleSkipForwardsTo(currentTime);
            }
            handleTimeEvent(currentTime, currentTime + lookAhead);
            _previousCheckedTime = currentTime;
        });
    }


    /**
     * The user skipped backwards in the media player.
     *
     * Sub-classes should call the parent method first.
     */
    void handleSkipBackwardsTo(int currentTime) {
        // Time jump backwards
        for (RepeatMediaAlertEvent event in _repeaters) {
            event.timeJump(currentTime);
        }
        while (_oneTimePast.isNotEmpty) {
            OnceMediaAlertEvent prev = _oneTimePast.last;
            if (prev.millisecond <= currentTime) {
                // No more previous entries are before right now.
                break;
            }
            // The past events are sorted in reverse order.
            _oneTimePast.removeLast();
            // So are the future ones
            _oneTimeFuture.add(prev);
        }
    }


    /**
     * The user skipped fowards in the media player.
     *
     * Sub-classes should call the parent method first.
     */
    void handleSkipForwardsTo(int currentTime) {
        // Time jump fowards
        for (RepeatMediaAlertEvent event in _repeaters) {
            event.timeJump(currentTime);
        }
        while (_oneTimeFuture.isNotEmpty) {
            OnceMediaAlertEvent next = _oneTimeFuture.last;
            if (next.millisecond <= currentTime) {
                break;
            }
            _oneTimeFuture.removeLast();
            _oneTimePast.add(next);

            // Do not trigger these.
        }
    }



    /**
     * Sub-classes should call the parent method first.
     *
     * @param currentTime the current time (milliseconds) as reported by the
     *      media player.
     * @param allowedToRunUpToThreshold the allowed time (milliseconds) which
     *      events triggered up to that point are allowed to run.  This is a
     *      threshold ahead of the currentTime.
     */
    void handleTimeEvent(int currentTime, int allowedToRunUpToThreshold) {
        for (RepeatMediaAlertEvent event in _repeaters) {
            if (event.nextTriggerTime < allowedToRunUpToThreshold) {
                event.invoke(currentTime);
            }
        }
        while (_oneTimeFuture.isNotEmpty) {
            OnceMediaAlertEvent next = _oneTimeFuture.last;
            if (next.millisecond >= allowedToRunUpToThreshold) {
                break;
            }
            _oneTimeFuture.removeLast();
            _oneTimePast.add(next);
            next.listener(currentTime);
        }

    }


    @override
    void stop() {
        if (_current != null) {
            _current = null;
            _timer.cancel();
            _timer = null;
        }
    }
}


/**
 * Generic class that represents an event to call at a media time.
 */
abstract class MediaAlertEvent {
    OnTimedAlert get listener;

    factory MediaAlertEvent.once(int millisecond, OnTimedAlert listener) {
        return new OnceMediaAlertEvent(millisecond, listener);
    }

    factory MediaAlertEvent.repeat(int repeatsEvery, OnTimedAlert listener) {
        return new RepeatMediaAlertEvent(repeatsEvery, listener);
    }
}


/**
 * An event that is called at only one media time point.
 */
class OnceMediaAlertEvent implements MediaAlertEvent {
    final int millisecond;
    final OnTimedAlert _listener;

    @override
    OnTimedAlert get listener => _listener;

    OnceMediaAlertEvent(this.millisecond, this._listener) {
        if (millisecond == null || millisecond < 0 || _listener == null) {
            throw new Exception("Illegal arguments");
        }
    }
}


/**
 * Comparator for [OnceMediaAlertEvent].
 */
int OnceMediaAlertEventCompare(OnceMediaAlertEvent e1,
        OnceMediaAlertEvent e2) {
    return e1.millisecond - e2.millisecond;
}


/**
 * Comparator for [OnceMediaAlertEvent].
 */
int OnceMediaAlertEventCompareReversed(OnceMediaAlertEvent e1,
        OnceMediaAlertEvent e2) {
    return e2.millisecond - e1.millisecond;
}



/**
 * An event that occurs at an interval.
 */
class RepeatMediaAlertEvent implements MediaAlertEvent {
    final OnTimedAlert _listener;
    final int repeatsEvery;
    int nextTriggerTime;

    @override
    OnTimedAlert get listener => _listener;

    RepeatMediaAlertEvent(this.repeatsEvery,
            this._listener) {
        if (_listener == null ||
                this.repeatsEvery == null || this.repeatsEvery <= 0) {
            throw new Exception("Illegal arguments");
        }
    }

    void timeJump(int millisecond) {
        // Align the next trigger time to be after the current millisecond
        // time, but on the repeatsEvery marker.
        nextTriggerTime = repeatsEvery +
                millisecond - (millisecond % repeatsEvery);
    }


    void invoke(int millisecond) {
        nextTriggerTime += repeatsEvery;
        _listener(millisecond);
    }
}
