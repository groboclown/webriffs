library stopwatch_media;

import 'dart:async';

import 'package:angular/angular.dart';
//import 'package:logging/logging.dart';

import '../../json/branch_details.dart';

import 'media_status.dart';

/**
 * Exposes the service to the wrapping component.  It's also used in the
 * component as the state and the controller.
 */
class StopwatchMediaStatusService extends MediaStatusService {
    Stopwatch _stopwatch = new Stopwatch();
    MediaStatus _status = MediaStatus.ENDED;
    final List<OnStatusChange> _listeners = [];
    final BranchDetails _branchDetails;
    int _baseTimeMillis = 0;

    StopwatchMediaStatusService(this._branchDetails);

    @override
    int get currentTimeMillis => _baseTimeMillis +
        _stopwatch.elapsedMilliseconds;

    @override
    MediaStatus get status => _status;

    @override
    String get htmlTag => 'stopwatch-media';

    @override
    BranchDetails get branchDetails => _branchDetails;

    @override
    void addStatusChangeListener(OnStatusChange listener) {
        if (listener != null) {
            _listeners.add(listener);
        }
    }

    @override
    void removeStatusChangeListener(OnStatusChange listener) {
        if (listener != null) {
            _listeners.remove(listener);
        }
    }



    /**
     * Force a stop in the playback, such as when the user navigates away
     * from the page.
     */
    @override
    void forceStop() {
        stop();
    }


    void setTime(int millis) {
        if (millis < 0 || millis == null) {
            throw new Exception("bad time: ${millis}");
        }
        _baseTimeMillis = millis;
        _stopwatch.reset();
    }


    void stop() {
        _stopwatch.stop();
        _stopwatch.reset();
        _baseTimeMillis = 0;
        _status = MediaStatus.ENDED;
        _fireEvent();
    }


    void start() {
        _stopwatch.start();
        _status = MediaStatus.PLAYING;
        _fireEvent();
    }


    void pause() {
        _stopwatch.stop();
        _status = MediaStatus.PAUSED;
        _fireEvent();
    }


    void _fireEvent() {
        for (OnStatusChange listener in _listeners) {
            listener(this);
        }
    }
}


@Component(
    selector: 'stopwatch-media',
    templateUrl: 'packages/webriffs_client/component/media/stopwatch_media_component.html',
    publishAs: 'cmp')
class StopwatchMediaComponent extends AbstractMediaStatusComponent {
    //static final Logger _log = new Logger('media.StopwatchMedia');

    StopwatchMediaStatusService _media;
    Timer _repeater;

    String timeField;

    bool hasTimeFieldFormatError = false;

    bool get loaded => _media != null;

    MediaStatus get status => _media == null
            ? MediaStatus.ENDED
            : _media.status;

    String time = "0:00:00.0";

    void start() {
        if (_media != null) {
            _media.start();
            if (_repeater == null) {
                _repeater = new Timer.periodic(new Duration(microseconds: 10),
                    (Timer timer) {
                        if (_media != null) {
                            time = toTimeStr(_media.currentTimeMillis);
                        }
                    });
            }
        }
    }

    void stop() {
        if (_media != null) {
            _media.stop();
        }
        if (_repeater != null) {
            _repeater.cancel();
            _repeater = null;
        }
        time = "0:00:00.0";
    }

    void pause() {
        if (_media != null) {
            _media.pause();
        }
        if (_repeater != null) {
            _repeater.cancel();
            _repeater = null;
        }
    }


    @NgOneWay('media')
    @override
    set media(Future<MediaStatusService> serviceFuture) {
        serviceFuture.then((MediaStatusService service) {
            if (service is StopwatchMediaStatusService) {
                _media = service;
            } else {
                throw new Exception("Invalid media status service ${service}");
            }
        });
    }


    void setTime() {
        //_log.info("Setting the time to [${timeField}]");
        if (_media != null && timeField != null && timeField.length > 0) {
            hasTimeFieldFormatError = false;

            RegExp exp = new RegExp(
                r"^\s*(?:(\d+)\s*:\s*)?(?:(\d+)\s*:\s*)?(\d+|\.\d+|\d+\.\d+|\d+\.)\s*$");
            List<Match> matches = new List.from(exp.allMatches(timeField));

            if (matches.length != 1) {
                hasTimeFieldFormatError = true;
                //_log.info("No match for [${timeField}]");
                return;
            }

            Match match = matches[0];
            //_log.info("Matched on [" + match[0] + "]");

            int hours = 0;
            int minutes = 0;
            double seconds = 0.0;

            /* DEBUG
            String s = "";
            for (int i = 1; i <= match.groupCount; ++i) {
                s += " ${i} = ${match[i]};";
            }
            _log.info("Groups:${s}");
            */
            if (match[3] == null) {
                throw new Exception("Unexpected regex state: [" +
                        match[0] + "]");
            }
            seconds = double.parse(match[3]);
            if (match[1] != null) {
                if (match[2] != null) {
                    // both hour and minute set
                    hours = int.parse(match[1]);
                    minutes = int.parse(match[2]);
                } else {
                    // only minute set
                    minutes = int.parse(match[1]);
                }
            } else if (match[2] != null) {
                    throw new Exception("Unexpected regex state: [" +
                            match[0] + "]");
            }

            int millis = (hours * 60 * 60 * 1000) +
                    (minutes * 60 * 1000) +
                    (seconds * 1000).toInt();

            _media.setTime(millis);
            time = toTimeStr(_media.currentTimeMillis);
        }
    }


    static String toTimeStr(int millis) {
        int c = (millis ~/ 10);
        // centi-seconds
        var t1 = (c % 100).toString();
        if (t1.length < 2) {
            t1 = "0" + t1;
        }

        // seconds
        c = (c ~/ 100);
        var t2 = (c % 60).toString();
        if (t2.length < 2) {
            t2 = "0" + t2;
        }

        // minutes
        c = (c ~/ 60);
        var t3 = (c % 60).toString();
        if (t3.length < 2) {
            t3 = "0" + t3;
        }

        // hours
        c = (c ~/ 60);
        return c.toString() + ":" + t3 + ":" + t2 + "." + t1;
    }
}

