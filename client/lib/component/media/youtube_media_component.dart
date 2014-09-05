library youtube_media;

import 'dart:async';
import 'dart:js';

import 'package:angular/angular.dart';
//import 'package:logging/logging.dart';

import '../../json/branch_details.dart';
import '../../json/film_details.dart';

import 'media_status.dart';

/**
 * Exposes the service to the wrapping component.  It's also used in the
 * component as the state and the controller.
 *
 * FIXME wire up the object to the player's list of callbacks on state and
 * error.  This should be a timer that keeps checking if the object is
 * null or not, and when not null, will register functions.
 */
class YouTubeMediaStatusService extends MediaStatusService {
    static final String YOUTUBE_LINK_URL = "https://youtube.com/watch?v=";


    MediaStatus _status = MediaStatus.ENDED;
    final List<OnStatusChange> _listeners = [];
    final BranchDetails _branchDetails;
    int _baseTimeMillis = 0;
    JsObject _yt;
    String _videoId;

    YouTubeMediaStatusService(this._branchDetails) {
        if (findYoutubeVideoId(_branchDetails) == null) {
            throw new Exception("Invalid branch: no youtube link");
        }
        _searchForYouTubeObj();
    }

    @override
    int get currentTimeMillis => _yt == null
        ? 0
        : (_yt.callMethod('getPlaybackSeconds', []) * 1000).toInt();

    @override
    MediaStatus get status {
        if (_yt == null) {
            return MediaStatus.ENDED;
        } else {
            num status = _yt['lastPlayerState'];
            switch (status) {
                case 1:
                    return MediaStatus.PLAYING;
                case 2:
                    return MediaStatus.PAUSED;
                case 3:
                case 4:
                    return MediaStatus.BUFFERING;
                default:
                    return MediaStatus.ENDED;
            }
        }
    }

    @override
    String get htmlTag => 'youtube-media';

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


    @override
    void forceStop() {
        stop();
    }


    void stop() {
        if (_yt != null) {
            _yt.callMethod('stop', []);
        }
    }


    void start() {
        if (_yt != null) {
            _yt.callMethod('play', []);
        }
    }


    void pause() {
        if (_yt != null) {
            _yt.callMethod('play', []);
        }
    }


    void _fireEvent() {
        for (OnStatusChange listener in _listeners) {
            listener(this);
        }
    }


    void _searchForYouTubeObj() {
        var obj = context['media_config'];
        if (obj == null) {
            new Timer(new Duration(microseconds: 2), () {
                _searchForYouTubeObj();
            });
            return;
        }
        if (obj is JsObject) {
            // assume it's the right object
            obj.callMethod('setVideoId',
                    [ findYoutubeVideoId(branchDetails) ]);
            _yt = obj;
        } else {
            throw new Exception("Bad JS value for media_config");
        }
    }


    static String findYoutubeVideoId(BranchDetails details) {
        for (LinkRecord link in details.filmLinks) {
            if (link.isDefined &&
                    link.urlPrefix == YOUTUBE_LINK_URL) {
                return link.serverUri;
            }
        }
        return null;
    }
}


@Component(
    selector: 'youtube-media',
    templateUrl: 'packages/webriffs_client/component/media/youtube_media_component.html',
    publishAs: 'cmp')
class YouTubeMediaComponent extends AbstractMediaStatusComponent {
    //static final Logger _log = new Logger('media.StopwatchMedia');

    YouTubeMediaStatusService _media;

    bool get loaded => _media != null;

    MediaStatus get status => _media == null
            ? MediaStatus.ENDED
            : _media.status;

    @NgOneWay('media')
    @override
    set media(Future<MediaStatusService> serviceFuture) {
        serviceFuture.then((MediaStatusService service) {
            if (service is YouTubeMediaStatusService) {
                _media = service;
            } else {
                throw new Exception("Invalid media status service ${service}");
            }
        });
    }

}

