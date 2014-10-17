library media_component;

import 'dart:async';
import 'dart:html';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';
import 'package:videoplay/depot.dart';

import '../../json/branch_details.dart';
import '../../json/film_details.dart';

import '../../util/time_format.dart';

import 'alert_controller.dart';
import 'stopwatch_media.dart';


/**
 * The generic top-level media component that loads the dynamically loaded
 * media component, based on the media type.
 *
 * When the page is changes, this will force the underlying media component
 * to stop.
 */
@Component(
    selector: 'media-controller',
    templateUrl: 'packages/webriffs_client/component/media/media_component.html',
    publishAs: 'cmp')
class MediaComponent implements ShadowRootAware, DetachAware {
    final Logger _log = new Logger('components.MediaComponent');

    final StopWatchSubComponent stopwatch = new StopWatchSubComponent();

    BranchDetails _details;
    VideoPlayer _realPlayer;

    Completer<BranchDetails> _detailsFuture = new Completer<BranchDetails>();

    // Setting dependencies:
    //  - enter/exit depends upon both the player and alerts to be set.
    Completer _onReady = new Completer();

    Completer<VideoPlayer> _player = new Completer<VideoPlayer>();

    bool get isStopwatch => _providerSet && ! _hasProvider;
    bool _providerSet = false;
    bool _hasProvider = false;

    bool get loadedPlayer => _realPlayer != null && ! stopwatch.loaded;
    bool get loadedStopwatch => stopwatch.loaded;
    bool get notLoaded => _realPlayer == null;


    @NgOneWay('controller')
    MediaAlertController alerts;


    @NgOneWay('time')
    set timeProvider(VideoPlayerTimeProvider p) {
        print("time provider set");
        _player.future.then((VideoPlayer vp) { p.player = vp; });
    }


    @NgOneWay('branch-details')
    set branchDetails(Future<BranchDetails> details) {
        if (_details != null) {
            throw new Exception("Invalid state: branchDetails already set");
        }
        if (details == null) {
            throw new Exception("Invalid state: null details future");
        }
        details.then((BranchDetails bd) {
            _details = bd;
            _detailsFuture.complete(bd);
            _providerSet = true;
        });
    }


    Future<BranchDetails> get branchDetails {
        return _detailsFuture.future;
    }

    Future<VideoPlayer> get media {
        return _player.future;
    }

    @override
    void detach() {
        if (_realPlayer != null) {
            _realPlayer.destroy();
            _realPlayer = null;
        }
    }

    @override
    void onShadowRoot(ShadowRoot shadowRoot) {
        // This is called after the attach() method, so we can't use the
        // attach - that would make the wrapping element null.

        DivElement wrappingElement = shadowRoot.querySelector('#media');
        if (wrappingElement == null) {
            throw new Exception("no media dom element found");
        }

        // Each attach requires re-embedding the video player
        _detailsFuture.future.then((BranchDetails bd) {
            LinkRecord link = findProviderLink(bd);
            if (link == null) {
                _hasProvider = false;
                stopwatch.media = new StopwatchMedia();
                return new Future.value(stopwatch.media);
            } else {
                VideoPlayerProvider provider =
                        getVideoProviderByName(link.mediaProvider);
                VideoProviderAttributes attributes =
                        provider.createAttributes();
                // FIXME set width and height and other attributes
                return embedVideo(provider, wrappingElement, link.uri,
                        attributes);
            }
        })
        .then((VideoPlayer player) {
            _realPlayer = player;
            MediaAlertController.connectPlayer(player, alerts);
            _player.complete(player);
        }).catchError((Object error, StackTrace stack) {
            _log.severe("Error loading details", error, stack);
            _detailsFuture.completeError(error, stack);
            _player.completeError(error, stack);
        });
    }


    static LinkRecord findProviderLink(BranchDetails bd) {
        if (bd == null) {
            return null;
        }
        for (LinkRecord link in bd.filmLinks) {
            if (link.isPlaybackMedia) {
                return link;
            }
        }
        return null;
    }
}




class StopWatchSubComponent {
    //static final Logger _log = new Logger('media.StopwatchMedia');

    StopwatchMedia _media;

    set media(StopwatchMedia m) {
        if (_media != null) {
            throw new Exception("already set media");
        }
        _media = m;
    }

    StopwatchMedia get media => _media;

    Timer _repeater;

    final TimeDisplayEdit _timeEdit = new TimeDisplayEdit();

    String get timeField => _timeEdit.timeField;
    set timeField(String s) { _timeEdit.timeField = s; }

    String get time => _media.timeDialation.displayString(
            _media.playbackTime.inMilliseconds / 1000.0);

    bool get hasTimeFieldFormatError => _timeEdit.formatError;

    String get dialation => _timeEdit.dialation.name;

    // FIXME allow the user to set the time dialation

    bool get loaded => _media != null;

    VideoPlayerStatus get status => _media == null
            ? VideoPlayerStatus.ENDED
            : _media.status;

    void start() {
        if (_media != null) {
            _media.play();
            if (_repeater == null) {
                _repeater = new Timer.periodic(new Duration(microseconds: 10),
                    (Timer timer) {
                        if (_media != null) {
                            _timeEdit.actualSeconds =
                                    _media.playbackTime.inMilliseconds / 1000.0;
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
        _timeEdit.actualSeconds = 0.0;
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

    void adjustTime(int millisChange) {
        if (_media != null) {
            int newTime = _media.playbackTime.inMilliseconds + millisChange;
            if (newTime < 0) {
                newTime = 0;
            }
            _media.setTime(newTime);
            _timeEdit.actualSeconds = newTime / 1000.0;
        }
    }

    void setTime() {
        if (_media != null && ! hasTimeFieldFormatError) {
            _media.setTime((_timeEdit.actualSeconds * 1000.0).toInt());
        }
    }
}

