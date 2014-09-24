library media_status;

import 'dart:async';

import 'package:logging/logging.dart';

import '../../json/branch_details.dart';
import '../../json/film_details.dart';

import 'alert_controller.dart';

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
 *
 * This includes logic to communicate with components to let them know about
 * the page visibility state.  Components should assume that the page is
 * not visible by default until the [pageLoaded()] method is called.
 */
abstract class MediaStatusService {
    int get currentTimeMillis;
    MediaStatus get status;
    BranchDetails get branchDetails;
    MediaAlertController get alertController;
    set alertController(MediaAlertController controller);

    /**
     * The AngularDart HTML tag name (without the brackets) for the component.
     */
    String get htmlTag;

    // FIXME the proper Dart way for handling events is to use a Stream.
    void addStatusChangeListener(OnStatusChange listener);
    void removeStatusChangeListener(OnStatusChange listener);

    /**
     * Called when the user navigates away from the page.  Media components
     * should use this to force media playback.
     */
    void pageUnloaded();

    /**
     * Called when the page is loaded.
     */
    void pageLoaded();
}



/**
 * Based on the given [LinkRecord] (which must have the `isPlaybackMedia`
 * flag set), returns either `null` (it's not a match) or the
 * [MediaStatusService] associated with this link.
 */
typedef Future<MediaStatusService> MediaStatusServiceFactory(
    BranchDetails branchDetails, LinkRecord link);



/**
 * Used by the wrapping UI component to communicate with the MediaStatusService
 * when the service can be loaded at some future point.
 */
class MediaStatusServiceConnector implements MediaStatusService {
    final Logger _log = new Logger('components.MediaStatusServiceConnector');
    final Completer<MediaStatusService> _media =
            new Completer<MediaStatusService>();
    MediaStatusService _realMedia;

    Future<MediaStatusService> get connection => _media.future;

    bool get isConnected => _media.isCompleted;

    void connect(Future<MediaStatusService> mediaFuture) {
        _log.info("Connecting to a future media service");
        mediaFuture.then((MediaStatusService media) {
            _log.info("Connected to media " + media.runtimeType.toString() +
                    " - " + media.htmlTag);
            if (_realMedia != null && _realMedia != media) {
                throw new Exception(
                        "Invalid state: connected to different media");
            }
            _realMedia = media;
            if (! _media.isCompleted) {
                _log.info("Completed the media service future");
                _media.complete(media);
            } else {
                _log.info("Duplicate call to the connect?");
            }
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
    MediaAlertController get alertController => _realMedia == null
            ? null
            : _realMedia.alertController;

    @override
    set alertController(MediaAlertController controller) =>
            _media.future.then((MediaStatusService media) {
                media.alertController = controller;
            });

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
    void pageUnloaded() {
        _media.future.then((MediaStatusService media) {
            media.pageUnloaded();
        });
    }

    @override
    void pageLoaded() {
        _media.future.then((MediaStatusService media) {
            media.pageLoaded();
        });
    }
}


/**
 * Standard top-level implementation details for the MediaStatusService.
 */
abstract class AbstractMediaStatusService implements MediaStatusService {
    final BranchDetails _branchDetails;
    final String _htmlTag;
    final List<OnStatusChange> _listeners = [];
    bool _pageVisible = false;
    MediaAlertController _alertController;

    bool get isPageVisible => _pageVisible;

    @override
    BranchDetails get branchDetails => _branchDetails;

    /**
     * The AngularDart HTML tag name (without the brackets) for the component.
     */
    String get htmlTag => _htmlTag;

    @override
    MediaAlertController get alertController => _alertController;

    @override
    set alertController(MediaAlertController controller) {
        if (_alertController != null) {
            _alertController.stop();
        }
        _alertController = controller;
    }


    AbstractMediaStatusService(this._htmlTag, this._branchDetails) {
        if (_htmlTag == null) {
            throw new Exception("htmlTag must not be null");
        }
        if (_branchDetails == null) {
            throw new Exception("branchDetails must not be null");
        }
    }

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

    @override
    void pageLoaded() {
        _pageVisible = true;
        fireChange();
    }

    @override
    void pageUnloaded() {
        _pageVisible = false;
        fireChange();
    }

    void fireChange() {
        for (OnStatusChange listener in _listeners) {
            listener(this);
        }
    }

    void onStart() {
        _alertController.start(() => currentTimeMillis);
        fireChange();
    }

    void onStop() {
        _alertController.stop();
        fireChange();
    }
}



/**
 * AngularDart component that the `MediaStatusService` references from its
 * `htmlTag` attribute.
 *
 * These are dynamically loaded, and may not have complete access to the
 * normal scope, such as the RouteProvider.  Therefore, page leave and
 *
 */
abstract class AbstractMediaStatusComponent {
    // Rather than set the annotation in the parent class, we require the
    // children to declare it.
    //@NgOneWay('media')
    set media(Future<MediaStatusService> service);
}


