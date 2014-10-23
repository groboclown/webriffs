
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
 *
 * FIXME add "is editing" mode for quips.  This is turned on and off only by
 *  the presence of a pending change on the branch.  This will mean an
 *  extension to the branch details to get that additional info, maybe?
 *  It should be in a request that returns BEFORE the branch details
 *  future returns.  Changing this state may have large implications on
 *  the displayed UI.
 *
 * FIXME should not have the change ID as part of the UI.  Instead, it
 * should be just a notion of determining what has changed based on what
 * the user is currently viewing.  The "branch updates" component needs to
 * be better integrated into this, so that it detects what quips were added,
 * and by whom, and then pushes those down to consumers (Stream events).
 * When a user is editing, they are in a "pending merge" state, to allow for
 * an easier merge click-through.
 *
 * The change ID can be part of the UI if the user wants to look at historical
 * versions of the branch.
 *
 *
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

    bool speechEntry = false;

    bool speechCapturing = false;

    bool get speechListening => _recognition == null ? false :
        _recognition.isCapturing;

    // TODO make user editable
    double displayDuration = 1.0;


    final VideoPlayerTimeProvider videoTimeProvider =
            new VideoPlayerTimeProvider();

    // Should never be null.
    QuipDetails pendingQuip = new QuipDetails.pending();

    // TODO look at using time_format.dart/TimeDisplayEdit
    int _quipTime;
    String _quipTimeStr;
    String quipText;
    bool get quipModified => (quipText != pendingQuip.text) ||
            (_quipTime != pendingQuip.timestamp);
    String get quipTime => _quipTimeStr;
    String _quipTimeError = null;
    bool get hasQuipTimeFormatError => _quipTimeError != null;
    final List<SpeechResult> spoken = [];


    set quipTime(String timestr) {
        if (timestr == null || timestr.length <= 0) {
            _quipTime = null;
            _quipTimeStr = null;
        } else {
            try {
                _quipTime = videoTimeProvider.convertToServerTime(timestr);
                _quipTimeStr = videoTimeProvider.dialation.displayString(
                    _quipTime / 1000.0);
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

        if (_recognition != null) {
            _recognition.resultEvents.listen((SpeechResult r) {
                spoken.add(r);
            });
        }
    }

    void startSpeechListen() {
        if (_recognition != null) {
            speechEntry = true;
            speechCapturing = false;
            spoken.clear();
            _recognition.start();
        }
    }

    void stopSpeechListen() {
        cancelVoiceCapture();
        speechEntry = false;
        speechCapturing = false;
        spoken.clear();
        if (_recognition != null) {
            _recognition.end();
        }
    }


    void startVoiceCapture() {
        setPendingQuipTime();
        spoken.clear();
        if (_recognition != null) {
            speechEntry = true;
            speechCapturing = true;
        }
    }

    void saveVoiceCapture() {
        if (spoken.isNotEmpty && speechCapturing) {
            quipText = spoken.map((SpeechResult r) => r.best).join(', ');
        }
        cancelVoiceCapture();
    }

    void cancelVoiceCapture() {
        cancelEditQuip();
        spoken.clear();
        speechCapturing = false;
    }


    void editQuip(QuipDetails quip) {
        pendingQuip = quip;
        quipText = quip.text;
        _quipTimeStr = getQuipTime(quip);
        _quipTimeError = null;
        _quipTime = quip.timestamp;
    }

    void deleteQuip(QuipDetails quip) {
        branchDetails.then((BranchDetails branch) {
            if (branch.userCanEditQuips) {

            }
        });
    }

    void setPendingQuipTime() {
        _quipTime = videoTimeProvider.serverTime.inMilliseconds;
        _quipTimeStr = videoTimeProvider.dialation.
                displayString(_quipTime / 1000.0);
    }

    void cancelEditQuip() {
        pendingQuip = new QuipDetails.pending();
        quipText = null;
        quipTime = null;
    }

    void savePendingQuip() {
        // FIXME cache up changes and push them in intervals to make fewer
        // requests to the server.

        if (! quipModified) {
            return;
        }

        String text = quipText;
        int time = _quipTime;
        QuipDetails quip = pendingQuip;
        quipText = null;
        quipTime = null;
        pendingQuip = new QuipDetails.pending();

        branchDetails.then((BranchDetails branch) {
            // FIXME handle tags
            if (branch.userCanEditQuips) {
                if (! branch.userHasPendingChange) {
                    _server.createCsrfToken("create_change")
                    .then((String csrf) =>
                        _server.put("/branch/${branchId}/pending", csrf,
                                data: { 'changes': -1 }))
                    .then((ServerResponse response) {
                        branch.userHasPendingChange = true;

                        quip.text = text;
                        quip.timestamp = time;
                        quipPaging.saveQuip(quip);
                    });
                } else {
                    quip.text = text;
                    quip.timestamp = time;
                    quipPaging.saveQuip(quip);
                }
            } else {
                // FIXME report error
                throw new Exception("SAVE QUIP: permission denied");
            }
        });
    }


    /**
     * Load all the changes that have happened
     */
    Future<BranchDetails> _loadEditBranchChange(ServerStatusService server) {
        return branchDetails
            .then((BranchDetails details) =>
                    details.updateFromServer(server))
            .then((ServerResponse response) =>
                    server.get('/branch/${branchId}/pending', null))
            .then((ServerResponse response) {
                // FIXME this should return the pending changes between the
                // user's branch-from version and the head version, and it
                // should also include the head revision, so we can pull in the
                // head branch details.

                return branchDetails;
            });
    }


    String getQuipTime(QuipDetails qd) {
        return videoTimeProvider.dialation.displayString(qd.timestamp / 1000.0);
    }


    void commitChanges() {
        branchDetails.then((BranchDetails bd) {
            quipPaging.commitChanges();
            bd.userHasPendingChange = false;
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

