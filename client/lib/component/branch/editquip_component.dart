
library editquip_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/speech_recognition.dart';
import '../../util/event_util.dart';
import '../../util/time_format.dart';

import '../media/alert_controller.dart';

import 'quip_paging.dart';

/**
 * The UI component for editing a quip.
 */
@Component(
    selector: 'edit-quip',
    templateUrl: 'packages/webriffs_client/component/branch/editquip_component.html',
    publishAs: 'cmp')
class EditQuipComponent {
    final ServerStatusService _server;
    final UserService _user;
    final SpeechRecognitionApi _recognition;

    QuipPaging _masterList;

    @NgOneWay('time')
    MediaTimeProvider timeProvider;

    @NgOneWay('list')
    set masterList(QuipPaging qp) {
        _masterList = qp;
    }

    QuipDetails pendingQuip = new QuipDetails.pending();

    @NgOneWay('updates')
    set editedQuipChangedEvents(StreamProvider<QuipDetails> sp) {
        sp.stream.listen((QuipDetails qd) {
            // NOTE: this just overwrites any non-saved changes in the
            // previous pending quip.

            pendingQuip = qd;
        });
    }


    Completer<BranchDetails> _branchDetails = new Completer();
    BranchDetails _realBranchDetails;

    @NgOneWay('branch')
    set branchDetails(Future<BranchDetails> bdf) {
        bdf.then((BranchDetails bd) {
            _realBranchDetails = bd;
            _branchDetails.complete(bd);
        });
    }


    bool get canEditQuip => _realBranchDetails == null
            ? false
            : _realBranchDetails.userCanEditQuips;

    bool get canEditTag => _realBranchDetails == null
            ? false
            : _realBranchDetails.userCanEditQuipTags;

    EditQuipComponent(ServerStatusService server, UserService user) :
            _server = server,
            _user = user,
            _recognition = createSpeechRecognition();


    // FIXME look at whether this should be pushed down to the QuipDetails
    // display level, or if AngularDart is smart enough to automatically
    // update this value.
    // Pass in the QuipDetails.timestamp as the value here.
    String quipTime(int timestamp) {
        double time = timestamp / 1000.0;
        if (timeProvider != null) {
            return timeProvider.dialation.displayString(time);
        }
        return TimeDialation.NATIVE.displayString(time);
    }


    void savePendingQuip() {
        _branchDetails.future.then((BranchDetails branch) {
            if (canEditQuip) {
                // FIXME push the save

                // FIXME add quip to master list
            } else {
                // FIXME report error
            }
        });
    }

    void setPendingQuipTime() {
        if (timeProvider != null) {
            pendingQuip.timestamp = timeProvider.serverTime.inMilliseconds;
        //} else {
        //    _log.warning('media service is not connected');
        }
    }

    void setPendingQuipTimeAs(String timestr) {
        pendingQuip.timestamp = timeProvider.convertToServerTime(timestr);
    }

}

