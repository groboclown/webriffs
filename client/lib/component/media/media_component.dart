library media_component;

import 'dart:async';
import 'dart:html';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';
import 'package:videoplay/depot.dart';

import 'package:cookie/cookie.dart' as cookie;

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
    templateUrl: 'packages/webriffs_client/component/media/media_component.html')
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

    double dialationSrcValue;
    double dialationTgtValue;


    @NgOneWay('controller')
    MediaAlertController alerts;


    @NgOneWay('time')
    set timeProvider(VideoPlayerTimeProvider p) {
        stopwatch.setTimeProvider(p);
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



/**
 * This component uses cookies to store the user's preferences for the
 * time dialation.
 */
class StopWatchSubComponent {
    //static final Logger _log = new Logger('media.StopwatchMedia');

    StopwatchMedia _media;
    final Completer<StopwatchMedia> _mediaFuture =
            new Completer<StopwatchMedia>();

    set media(StopwatchMedia m) {
        if (_media != null) {
            throw new Exception("already set media");
        }
        _media = m;
        _mediaFuture.complete(m);
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
    double dialationValue = 1.0;

    bool get loaded => _media != null;

    bool showDialationDetails = false;

    bool showDialationHelp = false;

    VideoPlayerStatus get status => _media == null
            ? VideoPlayerStatus.ENDED
            : _media.status;

    VideoPlayerTimeProvider _timeProvider;

    StopWatchSubComponent() {
        String dialation = cookie.get('dialation');
        if (dialation != null) {
            configureDialation(double.parse(dialation));
        }
    }

    void toggleDialationDetails() {
        showDialationDetails = ! showDialationDetails;
    }


    void toggleDialationHelp() {
        showDialationHelp = ! showDialationHelp;
    }


    void setTimeProvider(VideoPlayerTimeProvider tp) {
        _timeProvider = tp;
    }


    void setDialation(TimeDialation td) {
        if (td == null) {
            return;
        }

        // set the cookie value.
        double tdVal = td.actualToDisplayRatio;
        if (tdVal == TimeDialation.NATIVE.actualToDisplayRatio) {
            tdVal = 0.0;
        } else if (tdVal == TimeDialation.NTSC_DVD.actualToDisplayRatio) {
            tdVal = -1.0;
        } else if (tdVal == TimeDialation.PAL_DVD.actualToDisplayRatio) {
            tdVal = -2.0;
        } else if (tdVal == TimeDialation.NTSC_TV_ON_PAL.actualToDisplayRatio) {
            tdVal = -3.0;
        } else if (tdVal == TimeDialation.PAL_TV_ON_NTSC.actualToDisplayRatio) {
            tdVal = -4.0;
        }
        cookie.set('dialation', tdVal.toString());

        // Force the reloading of the media provider when the dialation
        // reloads.

        // This doesn't propigate to the quip list.  A page reload will be
        // necessary.
        _mediaFuture.future.then((StopwatchMedia m) {
            _media.timeDialation = td;
            _timeProvider.player = _media;
            _timeEdit.dialation = td;
        });
    }


    void configureDialation(double ratio) {
        print("setting dialation to ${ratio}");
        // First, check for standard ratios.
        if (ratio == null || (ratio < 0.05 && ratio > -0.05)) { // NULL or 0
            setDialation(TimeDialation.NATIVE);
        } else if (ratio < -0.95 && ratio > -1.05) { // -1
            setDialation(TimeDialation.NTSC_DVD);
        } else if (ratio < -1.95 && ratio > -2.05) { // -2
            setDialation(TimeDialation.PAL_DVD);
        } else if (ratio < -2.95 && ratio > -3.05) { // -3
            setDialation(TimeDialation.NTSC_TV_ON_PAL);
        } else if (ratio < -3.95 && ratio > -4.05) { // -4
            setDialation(TimeDialation.PAL_TV_ON_NTSC);
        } else if (ratio < 0) {
            // Problem : bad ratio; do nothing
        } else {
            setDialation(new TimeDialation("Custom", ratio));
        }
    }

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

