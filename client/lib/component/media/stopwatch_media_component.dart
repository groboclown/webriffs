library stopwatch_media;

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

    StopwatchMediaStatusService(this._branchDetails);

    @override
    int get currentTimeMillis => _stopwatch.elapsedMilliseconds;

    @override
    MediaStatus get status => _status;

    @override
    String get htmlTag => 'FIXME';

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


    void stop() {
        _stopwatch.stop();
        _stopwatch.reset();
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


// FIXME add the component



