library media_status;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../json/branch_details.dart';


class MediaStatus {
    static const PLAYING = const MediaStatus._(true);
    static const PAUSED = const MediaStatus._(false);
    static const BUFFERING = const MediaStatus._(false);
    static const ENDED = const MediaStatus._(false);

    static get values => [ PLAYING, PAUSED, BUFFERING, ENDED ];

    final bool playing;
    final bool waiting;


    const MediaStatus._(bool play) :
        playing = play,
        waiting = ! play;
}


typedef void OnStatusChange(MediaStatusService status);


/**
 * Exposes state details and controls for the media player.
 */
abstract class MediaStatusService {
    int get currentTimeMillis;
    MediaStatus get status;
    BranchDetails get branchDetails;

    /**
     * The AngularDart HTML tag name (without the brackets) for the component.
     */
    String get htmlTag;


    void addStatusChangeListener(OnStatusChange listener);
    void removeStatusChangeListener(OnStatusChange listener);

    /**
     * Force a stop in the playback, such as when the user navigates away
     * from the page.
     */
    void forceStop();
}



class MediaStatusServiceConnector extends MediaStatusService {
    final Completer<MediaStatusService> _media =
            new Completer<MediaStatusService>();
    MediaStatusService _realMedia;

    Future<MediaStatusService> get connection => _media.future;

    bool get isConnected => _media.isCompleted;


    void connect(Future<MediaStatusService> mediaFuture) {
        mediaFuture.then((MediaStatusService media) {
            if (_realMedia != null && _realMedia != media) {
                throw new Exception(
                        "Invalid state: connected to different media");
            }
            _realMedia = media;
            _media.complete(media);
        });
    }


    @override
    int get currentTimeMillis =>
            (_realMedia == null)
                ? 0
                : _realMedia.currentTimeMillis;

    @override
    MediaStatus get status =>
            (_realMedia == null)
                ? MediaStatus.ENDED
                : _realMedia.status;

    @override
    BranchDetails get branchDetails =>
            (_realMedia == null)
                ? null
                : _realMedia.branchDetails;

    @override
    String get htmlTag =>
            (_realMedia == null)
                ? null
                : _realMedia.htmlTag;


    @override
    void addStatusChangeListener(OnStatusChange listener) {
        _media.future.then((MediaStatusService media) {
            media.addStatusChangeListener(listener);
        });
    }

    @override
    void removeStatusChangeListener(OnStatusChange listener) {
        _media.future.then((MediaStatusService media) {
            media.removeStatusChangeListener(listener);
        });
    }

    @override
    void forceStop() {
        if (_realMedia != null) {
            _realMedia.forceStop();
        }
    }
}



/**
 * AngularDart component that the `MediaStatusService` references from its
 * `htmlTag` attribute.
 */
abstract class AbstractMediaStatusComponent {
    @NgOneWay('media')
    set media(MediaStatusService service);
}


