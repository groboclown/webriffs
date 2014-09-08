library youtube_media;

import 'dart:async';
import 'dart:js';
import 'dart:html';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../json/branch_details.dart';
import '../../json/film_details.dart';

import 'media_status.dart';

/**
 * Exposes the service to the wrapping component.  It's also used in the
 * component as the state and the controller.
 *
 * There's a major issue with this.  The embedded youtube player div will
 * end up being deep in the shadow DOM (for the branch viewer, it's in
 * `document.querySelector("view-branch").shadowRoot.
 * querySelector("media-controller").shadowRoot.
 * querySelector("youtube-media").shadowRoot.querySelector("#youtube_player")`).
 * However, the swfobject doesn't know about  the shadow DOM, and can't find
 * this dom element.  This means we either put the player at the top level
 * and have it make massive breaks to the way the UI is setup, or we
 * alter the swfobject code to be able to take a DOM object instead of
 * a reference to the object.
 *
 * This will also affect how the swf element is discovered.
 *
 *
 *
 * FIXME wire up the object to the player's list of callbacks on state and
 * error.  This should be a timer that keeps checking if the object is
 * null or not, and when not null, will register functions.
 */
class YouTubeMediaStatusService extends AbstractMediaStatusService {
    static final Logger _log = new Logger('media.YoutubeMedia');
    static final String YOUTUBE_LINK_URL = "https://youtube.com/watch?v=";


    MediaStatus _status = MediaStatus.ENDED;
    int _baseTimeMillis = 0;
    JsObject _yt;

    YouTubeMediaStatusService(BranchDetails branchDetails) :
            super('youtube-media', branchDetails) {
        if (findYoutubeVideoId(branchDetails) == null) {
            throw new Exception("Invalid branch: no youtube link");
        }
    }

    bool get loaded => _yt != null;

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
            _yt.callMethod('pause', []);
        }
    }


    @override
    void pageLoaded() {
        super.pageLoaded();
        searchForYouTubeObj();
    }


    @override
    void pageUnloaded() {
        print("*** Flash page unloading call ***");
        super.pageUnloaded();
        DivElement outerContainer = querySelector("#outer_media_container");
        if (outerContainer != null) {
            print("Removing the flash player");
            outerContainer.innerHtml = "<div id='media_container'></div>";
        }
        _yt = null;
    }


    void searchForYouTubeObj() {
        var obj = context['media_config'];
        _log.info("**** media_config = ${obj}");
        if (obj == null) {
            if (isPageVisible) {
                _log.info("searching for youtube");
                new Timer(new Duration(microseconds: 200), () {
                    searchForYouTubeObj();
                });
            }
            return;
        }
        if (_yt == null) {
            if (obj is JsObject) {
                _log.info("found something like the youtube javascript object");

                // assume it's the right object
                obj.callMethod('setVideoId', [
                    findYoutubeVideoId(branchDetails) ]);
                _yt = obj;
            } else {
                throw new Exception("Bad JS value for media_config");
            }
        } else {
            _log.info("repeat call to the searchForYouTubeObj");
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
class YouTubeMediaComponent implements AbstractMediaStatusComponent {
    RouteHandle _route;
    YouTubeMediaStatusService _media;

    bool get loaded => _media != null;

    bool get youtubeLoading => _media == null
            ? true
            : ! _media.loaded;

    MediaStatus get status => _media == null
            ? MediaStatus.ENDED
            : _media.status;

    Completer<YouTubeMediaStatusService> _mediaCompleter =
            new Completer<YouTubeMediaStatusService>();

    @NgOneWay('media')
    @override
    set media(Future<MediaStatusService> serviceFuture) {
        print("Set the media service in the youtube component");
        serviceFuture.then((MediaStatusService service) {
            if (service is YouTubeMediaStatusService) {
                print("Media service loaded!!!");
                _mediaCompleter.complete(service);
                _media = service;
                if (_media.isPageVisible) {
                    print("Forcing a service search");
                    _media.searchForYouTubeObj();
                }
            } else {
                throw new Exception("Invalid media status service ${service}");
            }
        });
    }
}

