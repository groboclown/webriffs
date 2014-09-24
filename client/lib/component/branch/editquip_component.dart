
library editquip_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/user.dart';
import '../../service/server.dart';
import '../../json/branch_details.dart';
import '../../json/quip_details.dart';
import '../../util/speech_recognition.dart';
import '../media/media_status.dart';
import '../../util/event_util.dart';

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

    @NgOneWay('list')
    set masterList(QuipPaging qp) {
        _masterList = qp;
    }

    QuipDetails pendingQuip = new QuipDetails.pending();

    @NgOneWay('updates')
    set editedQuipChangedEvents(StreamProvider<QuipDetails> sp) {
        sp.stream.forEach((QuipDetails qd) {
            // FIXME update the pending quip to be this.

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


    MediaStatusServiceConnector _mediaStatusService;

    @NgOneWay('media')
    set mediaStatusService(MediaStatusServiceConnector mediaStatusService) {
        this._mediaStatusService = mediaStatusService;
    }

    bool get isMediaConnected => _mediaStatusService == null
            ? false
            : _mediaStatusService.isConnected;

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
        if (isMediaConnected) {
            pendingQuip.timestamp = _mediaStatusService.currentTimeMillis;
        //} else {
        //    _log.warning('media service is not connected');
        }
    }

    void setPendingQuipTimeAs(String timestr) {
        // FIXME parse the time string
    }

}

