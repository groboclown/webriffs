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
class MediaComponent extends ShadowRootAware implements DetachAware {
    final Logger _log = new Logger('components.MediaComponent');
    RouteHandle _route;

    final StopWatchSubComponent stopwatch = new StopWatchSubComponent();

    BranchDetails _details;
    VideoPlayer _realPlayer;

    Completer<BranchDetails> _detailsFuture = new Completer<BranchDetails>();

    // Setting dependencies:
    //  - enter/exit depends upon both the player and alerts to be set.
    Completer _onReady = new Completer();

    // Player depends upon the branch details being set and the
    // time sink.  Because the branch details are passed in as a future,
    // we'll setup a time sink then details then player create chain.
    Completer<Sink<TimeDialation>> _timeSink =
            new Completer<Sink<TimeDialation>>();
    Completer<Element> _wrappingElement = new Completer<Element>();

    Completer<VideoPlayer> _player = new Completer<VideoPlayer>();
    MediaAlertController _alerts;

    bool get isStopwatch => _providerSet && ! _hasProvider;
    bool _providerSet = false;
    bool _hasProvider = false;

    bool get loadedPlayer => _realPlayer != null && ! stopwatch.loaded;
    bool get loadedStopwatch => stopwatch.loaded;

    @NgOneWay('time-sink')
    set timeSink(Sink<TimeDialation> std) {
        // implicit: can only be set once
        _timeSink.complete(std);
    }


    @NgOneWay('controller')
    set controller(MediaAlertController mac) {
        _alerts = mac;
        if (_realPlayer != null) {
            // implicit: can only be set once
            _player.complete(_realPlayer);
        }
    }


    set _videoPlayer(VideoPlayer player) {
        _realPlayer = player;
        if (_alerts != null) {
            _player.complete(player);
        }
    }


    set timeProvider(VideoPlayerTimeProvider p) {
        _player.future.then((VideoPlayer vp) { p.player = vp; });
    }


    @NgOneWay('branch-details')
    set branchDetails(Future<BranchDetails> details) {
        if (_player != null) {
            throw new Exception("Invalid state: branchDetails already set");
        }
        if (details == null) {
            throw new Exception("Invalid state: null details future");
        }

        // See above about the chain ordering
        _timeSink.future
        .then((_) => details)
        .then((BranchDetails bd) {
            _detailsFuture.complete(bd);
            _providerSet = true;
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
                // FIXME set width and height
                return _wrappingElement.future.then((Element e) {
                    return embedVideo(provider, e, link.uri, attributes);
                });
            }
        })
        .then((VideoPlayer player) {
            _videoPlayer = player;
        }).catchError((Object error, StackTrace stack) {
            _log.severe("Error loading details", error, stack);
            _detailsFuture.completeError(error, stack);
            _player.completeError(error, stack);
        });
    }


    Future<BranchDetails> get branchDetails {
        return _detailsFuture.future;
    }

    Future<VideoPlayer> get media {
        return _player.future;
    }

    MediaComponent(RouteProvider routeProvider) {
        _route = routeProvider.route.newHandle();
        _route.onPreLeave.listen((RouteEvent e) {
            media.then((VideoPlayer player) {
                player.stop();
            });
        });
        //_route.onEnter.listen((RouteEvent e) {
        //    _onEnter();
        //});

        // At this point, the component is created and initialilzation begins.
        // Note that the onEnter won't be called for this situation.
        media.then((VideoPlayer player) {
            MediaAlertController.connectPlayer(player, _alerts);
        });
    }

    void detach() {
        // The route handle must be discarded.
        _route.discard();
        if (_realPlayer != null) {
            _realPlayer.destroy();
            _realPlayer = null;
        }
    }

    void _onEnter() {
        _log.info("MediaComponent page entered");
    }

    void onShadowRoot(ShadowRoot shadowRoot) {
        // FIXME all the future stuff above to deal with the initialization time
        // of the different components should instead be moved into here without
        // any futures.

        DivElement inner = shadowRoot.querySelector('#media');
        _wrappingElement.complete(inner);
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

    bool get hasTimeFieldFormatError => _timeEdit.formatError;
    String get time => _timeEdit.timeField;
    set time(String t) {
        _timeEdit.timeField = t;
        if (_media != null) {
            _media.setTime((_timeEdit.actualSeconds * 1000.0).toInt());
        }
    }

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
}

