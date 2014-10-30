
library editbranch_component;

import 'dart:async';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/speech_recognition.dart';

import '../media/alert_controller.dart';

import 'quip_paging.dart';
import 'abstract_branch_component.dart';


/**
 * The UI component view of the list of films.  The video timer controls are
 * embedded in this component, and are accessed by this component through the
 * `mediaStatusService` field.
 */
@Component(
    selector: 'edit-branch',
    templateUrl: 'packages/webriffs_client/component/branch/editbranch_component.html')
class EditBranchComponent extends AbstractBranchComponent {
    static final Logger _log = new Logger('media.EditBranchComponent');

    /** Converts the [displayDuration] into seconds, based on the number of
     * characters in the quip. */
    static final double DISPLAY_DURATION_SCALE = 0.1;

    /** Minimum time (seconds) to allow a quip to be shown; multiplied by the
     * [displayDuration]. */
    static final double MIN_DISPLAY_DURATION = 4.0;

    final ServerStatusService _server;
    final UserService _user;
    final LowSpeechService _recognition;
    final int requestedChangeId;

    final QuipPaging quipPaging;

    final List<QuipDetails> shownQuips = [];

    bool get hasSpeechSupport => _recognition != null;

    QuipMediaAlertController get mediaAlertController =>
            quipPaging.mediaAlertController;

    bool get noQuips => quipPaging.quips.isEmpty;

    // If the user can't edit the quips, or the user requested to see an old
    // version, then don't allow edits.
    bool get isEditable => canEditQuips && requestedChangeId < 0;

    VoiceCaptureController _voiceCapture;

    bool get speechEntry => _voiceCapture != null;
    bool get speechListening => _voiceCapture == null ? false :
        (! _voiceCapture.hasFatalError && _voiceCapture.isCapturing);
    String get heardText => _voiceCapture == null ? null :
        _voiceCapture.interimTranscript;
    Iterable<SpeechPhrase> get heardPhrases => _voiceCapture == null ? [] :
        _voiceCapture.phrases;
    String get speechErrorText => _voiceCapture == null ? null :
        _voiceCapture.errorText;
    SpeechError get speechError => _voiceCapture == null ? null :
        _voiceCapture.error;
    bool get hasSpeechError => speechError != null;

    // TODO make user editable
    double displayDuration = 1.0;


    final VideoPlayerTimeProvider videoTimeProvider =
            new VideoPlayerTimeProvider();

    // Should never be null.
    QuipDetails pendingQuip = new QuipDetails.pending();

    // TODO look at using time_format.dart/TimeDisplayEdit
    int _quipTime;
    String _quipTimeStr;
    String _parsedQuipTime;
    String quipText;
    bool get quipModified => (quipText != pendingQuip.text) ||
            (_quipTime != pendingQuip.timestamp);
    String _quipTimeError = null;
    bool get hasQuipTimeFormatError => _quipTimeError != null;
    String get quipTimeError => _quipTimeError;
    String get parsedQuipTime => _parsedQuipTime;
    bool get quipTimeEdited => _parsedQuipTime != _quipTimeStr;

    String get quipTime => _quipTimeStr;

    set quipTime(String timestr) {
        if (timestr == null || timestr.length <= 0) {
            _quipTime = null;
            _quipTimeStr = null;
            _quipTimeError = null;
            _parsedQuipTime = null;
        } else {
            try {
                _quipTime = videoTimeProvider.convertToServerTime(timestr);
                _parsedQuipTime = videoTimeProvider.dialation.displayString(
                    _quipTime / 1000.0);
                // NOTE: don't set _quipTimeStr
                _quipTimeError = null;
            } catch (e) {
                _quipTimeError = "Invalid time format";
            }
        }
    }


    factory EditBranchComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        List<int> ids = AbstractBranchComponent.
                parseRouteParameters(routeProvider);
        int branchId = ids[0];
        int changeId = ids[1];

        if (changeId == null) {
            // User didn't specify a change id, so use the "get the current"
            // as the change id.
            changeId = -1;
        }

        Future<BranchDetails> branchDetails = loadBranchDetails(server,
                branchId, changeId);

        QuipPaging quips = new QuipPaging(server, branchId, changeId);

        return new EditBranchComponent._(server, user, branchId, changeId,
                branchDetails, quips, changeId);
    }

    EditBranchComponent._(ServerStatusService server, UserService user,
            int branchId, int changeId, Future<BranchDetails> branchDetails,
            this.quipPaging, this.requestedChangeId) :
            _server = server,
            _user = user,
            _recognition = createSpeechService(),
            super(server, user, branchId, changeId, branchDetails) {

        // Link up the alerts, the video timing, and the UI

        quipPaging.mediaAlertController.setHandler((QuipDetails qd) {
            if (qd.text == null || qd.text.length <= 0) {
                return;
            }
            shownQuips.add(qd);

            double secondsDuration = qd.text.trim().length *
                    DISPLAY_DURATION_SCALE * displayDuration;
            double minDuration = MIN_DISPLAY_DURATION * displayDuration;

            if (secondsDuration < minDuration) {
                secondsDuration = minDuration;
            }

            // Remove the quip after this long.
            new Timer(new Duration(
                    milliseconds: (secondsDuration * 1000.0).toInt()), () {
               shownQuips.remove(qd);
            });
        });

        videoTimeProvider.attachToAlertController(
                quipPaging.mediaAlertController);

        branchDetails.then((BranchDetails bd) {
            if (bd.userHasPendingChange) {
                quipPaging.loadChange(null);
            } else {
                quipPaging.loadChange(changeId);
            }
        });
    }

    /**
     * Called when the user reloads; as such, we'll need to reload the quip
     * list when this returns.
     */
    @override
    Future<BranchDetails> reloadDetails() {
        return super.reloadDetails().then((BranchDetails bd) {
            quipPaging.reload();
            return bd;
        });
    }


    void startSpeechListen() {
        if (_recognition != null && _voiceCapture == null) {
            _voiceCapture = new VoiceCaptureController(_recognition);
        }
    }

    void stopSpeechListen() {
        cancelVoiceCapture();
        if (_voiceCapture != null) {
            _voiceCapture.close();
            _voiceCapture = null;
        }
    }


    void startVoiceCapture() {
        setPendingQuipTime();
        if (_voiceCapture != null && ! _voiceCapture.isCapturing) {
            _voiceCapture.start().then((String text) {
                text = text.trim();
                if (text.length > 0) {
                    quipText = text;
                    savePendingQuip();
                }
            });
        }
    }

    void clearVoiceText() {
        if (_voiceCapture != null) {
            _voiceCapture.clear();
        }
    }

    void saveVoiceCapture() {
        if (_voiceCapture != null) {
            _voiceCapture.end();
        }
    }

    void cancelVoiceCapture() {
        if (_voiceCapture != null) {
            cancelEditQuip();
            _voiceCapture.cancel();
        }
    }


    void editQuip(QuipDetails quip) {
        pendingQuip = quip;
        quipText = quip.text;
        _quipTimeStr = getQuipTime(quip);
        _parsedQuipTime = _quipTimeStr;
        _quipTimeError = null;
        _quipTime = quip.timestamp;
    }

    void setPendingQuipTime() {
        Duration serverTime = videoTimeProvider.serverTime;
        if (serverTime != null) {
            _quipTime = serverTime.inMilliseconds;
            _quipTimeStr = videoTimeProvider.dialation.
                    displayString(_quipTime / 1000.0);
            _parsedQuipTime = _quipTimeStr;
        }
    }

    void cancelEditQuip() {
        pendingQuip = new QuipDetails.pending();
        quipText = null;
        quipTime = null;
    }


    Future<BranchDetails> _quipAlterSetup() {
        return branchDetails.then((BranchDetails branch) {
            if (branch.userCanEditQuips) {
                if (! branch.userHasPendingChange) {
                    return quipPaging.createPendingChange().then((_) {
                        branch.userHasPendingChange = true;
                        return branch;
                    });
                } else {
                    return branch;
                }
            }
            return null;
        });
    }

    void deleteQuip(QuipDetails quip) {
        _quipAlterSetup().then((BranchDetails branch) {
            if (branch != null) {
                quipPaging.deleteQuip(quip);
            }
        });
    }

    void savePendingQuip() {
        // The caching of quips for group pushes to the server is handled by
        // the paging structure.

        if (! quipModified) {
            return;
        }

        String text = quipText;
        int time = _quipTime;
        QuipDetails quip = pendingQuip;
        quipText = null;
        quipTime = null;
        pendingQuip = new QuipDetails.pending();

        _quipAlterSetup().then((BranchDetails branch) {
            if (branch != null) {
                quip.text = text;
                quip.timestamp = time;
                quipPaging.saveQuip(quip);
            }
        });
    }


    String getQuipTime(QuipDetails qd) {
        return videoTimeProvider.dialation.displayString(qd.timestamp / 1000.0);
    }


    void commitChanges() {
        branchDetails
        .then((BranchDetails bd) {
            quipPaging.commitChanges().then((ServerResponse resp) {
                if (! resp.wasError) {
                    bd.userHasPendingChange = false;
                }
            });
        });
    }


    void abandonChanges() {
        branchDetails.then((BranchDetails bd) {
            quipPaging.abandonChanges().then((bool success) {
                if (success) {
                    bd.userHasPendingChange = false;
                }
            });
        });
    }
}

