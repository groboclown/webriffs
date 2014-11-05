
library branchupdates_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../json/branch_details.dart';
import '../../util/paging.dart';

/**
 * List of the updates to the branch that happened after the currently
 * viewed change.
 *
 * FIXME this will need to take into account the updates for a pending
 * edit.  This includes quip changes.
 */
@Component(
    selector: 'branch-updates',
    templateUrl: 'packages/webriffs_client/component/branch/branchupdates_component.html')
class BranchUpdatesComponent implements AttachAware, DetachAware {
    static final Duration TIMER_REPEAT = new Duration(seconds: 60);

    final ServerStatusService _server;
    final UserService _user;
    PageState _pageState;
    Timer _repeater;

    @NgOneWayOneTime('current-branch')
    set branchDetailsFuture(Future<BranchDetails> bdf) {
        bdf.then((BranchDetails bd) {
            _branchDetails = bd;
            _setup();
        });
    }

    @NgOneWayOneTime('max-change-count')
    int maximumChangesShown = 5;

    BranchDetails _branchDetails;

    List<ChangeDetails> _changes;

    int _changesSinceCount = 0;


    bool get hasError => _pageState == null ? false : _pageState.hasError;
    String get errorMessage => _pageState == null ? "" : _pageState.errorMessage;

    int get viewingChangeId =>
            _branchDetails == null ? null : _branchDetails.changeId;
    String get viewingUpdatedOn =>
            _branchDetails == null ? null : _branchDetails.updatedOn;

    bool get hasUpdates => _changesSinceCount > 0;
    bool get hasNoUpdates => _pageState != null && _changesSinceCount <= 0;
    int get changesSinceCount => _changesSinceCount;
    List<ChangeDetails> get changes => _changes;
    bool get hasMoreChangesThanFetched => _changesSinceCount > _changes.length;


    BranchUpdatesComponent(this._server, this._user);


    /**
     * Initial setup of the component once the branch details are loaded.
     */
    void _setup() {
        load().then((_) {
            if (_pageState == null || ! _pageState.hasError) {
                _repeater = new Timer.periodic(TIMER_REPEAT, (_) => load());
            }
        });
    }


    /**
     * Load the change list.
     */
    Future<BranchUpdatesComponent> load() {
        if (_branchDetails != null) {
            if (_pageState == null) {
                _pageState = new PageState(this._server,
                    "/branch/" + _branchDetails.branchId.toString() +
                    "/version",
                    (PageState ps, Iterable<dynamic> data, ServerResponse sr) {
                        if (! sr.wasError) {
                            return _processData(ps.recordCount, data, sr);
                        } else {
                            if (_repeater != null) {
                                _repeater.cancel();
                            }
                        }
                        return null;
                    }, delay: TIMER_REPEAT);
            }

            return _pageState.updateFromServer(
                    nextPage: 0,
                    newRecordsPerPage: maximumChangesShown,
                    newSortOrder: 'D',
                    newFilters: { 'versions_after': _branchDetails.changeId }).
                    then((_) => this);
        }
        return new Future<BranchUpdatesComponent>.value(this);
    }


    Future<ServerResponse> _processData(int recordCount,
            Iterable<dynamic> data, ServerResponse response) {
        _changesSinceCount = recordCount;
        List<ChangeDetails> newChanges = [];
        data.forEach((var d) {
            if (d != null && d is Map<String, dynamic>) {
                newChanges.add(new ChangeDetails.fromJson(_branchDetails,
                        d));
            }
        });
        return new Future<ServerResponse>.value(response);
    }


    @override
    void attach() {
        if (_branchDetails != null) {
            _setup();
        }
    }

    @override
    void detach() {
        _repeater.cancel();
    }
}



class ChangeDetails {
    final int changeId;
    final String branchName;
    final bool branchDifferent;
    final String description;
    final bool descriptionDifferent;
    final String updatedOn;


    factory ChangeDetails.fromJson(BranchDetails current,
            Map<String, dynamic> row) {
        int changeId = row['Gv_Change_Id'];
        String branchName = row['Branch_Name'];
        String description = row['Description'];
        String updatedOn = row['Updated_On'];
        // FIXME who made the change?

        bool branchDifferent = branchName != current.name;
        bool descriptionDifferent = description != current.description;

        return new ChangeDetails._(changeId, branchName, branchDifferent,
                description, descriptionDifferent, updatedOn);
    }

    ChangeDetails._(this.changeId, this.branchName, this.branchDifferent,
            this.description, this.descriptionDifferent,
            this.updatedOn);
}
