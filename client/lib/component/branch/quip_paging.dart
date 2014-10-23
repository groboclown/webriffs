
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../util/paging.dart';
import '../../json/quip_details.dart';

import '../media/alert_controller.dart';


/**
 * A deep cache for the quips.  It pages the quips in, but keeps a longer list
 * of the quips in memory than what the server returns.  The actual control
 * over when to page, and which data to clear, is up to the owning component.
 * It also manages the incremental updates to quips from the server.
 *
 * This uses the [PagingComponent] to help with the loading of the data and the
 * error / loading state.
 *
 * For now, the [QuipPanging] loads all the quips at the start.  Eventually,
 * this should be more of a streaming service.
 */
class QuipPaging implements AsyncComponent {
    final ServerStatusService _server;
    final List<QuipDetails> quips = [];
    final List<QuipDetails> _pendingSave = [];
    final QuipMediaAlertController mediaAlertController =
            new QuipMediaAlertController();
    final StreamController<QuipPaging> _onLoad =
            new StreamController.broadcast();
    final int branchId;

    final QuipUpdateComponent quipUpdates;

    /**
     * Updated with each change that's loaded in.  For pending changes, this
     * is `null`.
     */
    int changeId;


    Stream<QuipPaging> get onLoad => _onLoad.stream;

    bool _loadedError = false;
    bool _loading = false;
    bool _notLoaded = true;
    String _error = null;

    @override
    bool get loadedError => _loadedError;

    @override
    bool get loadedSuccessful => ! _loadedError;

    @override
    bool get loading => _loading;

    @override
    bool get notLoaded => _notLoaded;

    @override
    String get error => _error;

    bool get hasPendingSave => _pendingSave.isNotEmpty;

    int _percentLoaded;

    int get percentLoaded => _percentLoaded;


    factory QuipPaging(ServerStatusService server, int branchId, int changeId) {
        return new QuipPaging._(server, branchId, changeId);
    }

    /**
     * Fetch the quips that the user has pending a commit.
     */
    factory QuipPaging.pending(ServerStatusService server, int branchId) {
        return new QuipPaging._(server, branchId, null);
    }


    QuipPaging._(ServerStatusService server, int branchId, this.changeId) :
            _server = server,
            branchId = branchId,
            quipUpdates = new QuipUpdateComponent(server, branchId) {
        mediaAlertController.setQuips(quips);
    }


    Future saveQuip(QuipDetails quip) {
        if (quip.id == null) {
            return newQuip(quip);
        } else {
            return updateQuip(quip);
        }
    }


    /**
     * User added a new quip.  Insert it into the list and save to the server.
     */
    Future newQuip(QuipDetails pendingQuip) {
        return quipUpdates.newQuip(pendingQuip)
            .then((QuipDetails newQuip) {
                _mergeQuips([ newQuip ]);
            });
    }


    Future deleteQuip(QuipDetails quip) {
        if (quip.id == null && quip.changed) {
            // There's a potential issue here with this logic.  There's the
            // situation where a save could be in-transit for a new object,
            // and the user clicks on delete for it.
            // FIXME handle this situation!
            quips.remove(quip);
            return new Future.value();
        } else {
            quip.pendingDelete = true;
            return quipUpdates.deleteQuip(quip);
        }
    }


    /**
     * Save the existing quip to the server.
     */
    Future updateQuip(QuipDetails quip) {
        return new Future(() {
            // Updating the time in our list may take a long time, so
            // move it into a future.
            if (quip.committedTimestamp != quip.timestamp) {
                _updatedTime(quip);
            }

            return quipUpdates.updateQuip(quip);
        });
    }


    void _updatedTime(QuipDetails quip) {
        // update the location of the quip in the list.
        // Should perform a binary search for the current location of this
        // quip, followed by another one to find where to insert it.

        // For now, we'll do the simple route.
        quips.remove(quip);
        _mergeQuips([ quip ]);
    }


    void loadChange(int newChangeId) {
        changeId = newChangeId;
        reload();
    }

    @override
    void reload() {
        if (_loading) {
            return;
        }

        _loadedError = false;
        _loading = true;
        _notLoaded = true;
        _error = null;
        _percentLoaded = 0;
        quips.clear();
        mediaAlertController.nextIndex = 0;

        String path = null;
        if (changeId == null) {
            path = "/branch/${branchId}/pending/quip";
        } else {
            path = "/branch/${branchId}/version/${changeId}/quip";
        }
        PageState pg = new PageState(_server, path, (PageState pageState,
                Iterable<dynamic> data, ServerResponse response) {
            _loadedError = pageState.hasError;
            _error = pageState.errorMessage;
            if (_loadedError) {
                _loading = false;
                _notLoaded = true;
            } else {
                if (data != null) {
                    _mergeQuips(
                        new List.from(data.map((dynamic jsonData) =>
                            new QuipDetails.fromJson(branchId, jsonData))));
                }

                if (pageState.pageLastIndex >= pageState.recordCount) {
                    _loading = false;
                    _notLoaded = false;
                    _percentLoaded = 100;
                } else {
                    _percentLoaded =
                            pageState.pageLastIndex ~/ pageState.recordCount;

                    pageState.updateFromServer(
                            nextPage: pageState.currentPage + 1);
                }

            }
            _onLoad.add(this);
        });

        pg.updateFromServer(newRecordsPerPage: 100, newSortOrder: 'A');
    }


    /**
     * Merges a list of (sorted by timestamp) quips into the existing
     * list.
     */
    void _mergeQuips(List<QuipDetails> newQuips) {
        // Use a merge sort on the two sorted lists.
        // This could be optimized by performing a pseudo-binary search
        // look-ahead to see where the next insertion point would be.

        // FIXME update the QuipMediaAlertController index.

        int origPos = 0;
        int newPos = 0;
        while (origPos < quips.length || newPos < newQuips.length) {
            if (origPos >= quips.length) {
                quips.add(newQuips[newPos++]);
                origPos++;
            } else if (newPos >= newQuips.length) {
                // finished processing the new quips
                origPos = quips.length;
            } else if (newQuips[newPos].id == null) {
                // TODO may be eliminated in the future
                _error = "internal error";
                throw new Exception("Must not merge uncommited quips");
            } else if (quips[origPos].id == newQuips[newPos].id) {
                quips[origPos++] = newQuips[newPos++];
            } else if (quips[origPos].timestamp < newQuips[newPos].timestamp) {
                origPos++;
            } else { // if (quips[origPos].timestamp >= newQuips[newPos].timestamp) {
                quips.insert(origPos++, newQuips[newPos++]);
            }
        }

    }


    Future<ServerResponse> createPendingChange() {
        return quipUpdates.createPendingChange();
    }


    Future<ServerResponse> commitChanges() {
        return quipUpdates.commitChanges();
    }


    Future<bool> abandonChanges() {
        return quipUpdates.abandonChanges()
        .then((ServerResponse resp) {
            if (resp.wasError) {
                _error = resp.message;
                return false;
            } else {
                // We were editing a version, which means we weren't explicitly
                // looking at a historical version.  So reload the head version.
                loadChange(-1);
                return true;
            }
        });
    }
}


typedef void QuipDisplayHandler(QuipDetails quip);


/**
 * An extension to the [BaseMediaAlertController] that includes tight
 * integration with the [QuipPaging] class to reduce the overhead of two lists
 * of quips.
 */
class QuipMediaAlertController extends BaseMediaAlertController {
    // FIXME needs tighter integration with the QuipPaging model, for when
    // the times are updated.  That can throw off the current index.

    QuipDisplayHandler _handler;
    List<QuipDetails> _quips;
    int nextIndex;


    void setHandler(QuipDisplayHandler handler) {
        if (_handler != null) {
            throw new Exception("already set");
        }
        _handler = handler;
    }

    void setQuips(List<QuipDetails> quips) {
        if (_quips != null) {
            throw new Exception("already set");
        }
        _quips = quips;
    }

    @override
    void handleSkipBackwardsTo(int time) {
        super.handleSkipBackwardsTo(time);
        // Just in case the list size changed on us.
        if (nextIndex > _quips.length) {
            nextIndex = _quips.length;
        }
        while (nextIndex > 0 && _quips[nextIndex - 1].timestamp > time) {
            nextIndex--;
        }
    }

    @override
    void handleSkipForwardsTo(int time) {
        super.handleSkipForwardsTo(time);
        while (nextIndex < _quips.length &&
                _quips[nextIndex].timestamp < time) {
            nextIndex++;
        }
    }


    @override
    void handleTimeEvent(int currentTime, int allowedToRunUpToThreshold) {
        super.handleTimeEvent(currentTime, allowedToRunUpToThreshold);
        while (nextIndex < _quips.length &&
                _quips[nextIndex].timestamp < allowedToRunUpToThreshold) {
            print("=== time event ${currentTime} for ${_quips[nextIndex].text}");
            _handler(_quips[nextIndex]);
            nextIndex++;
        }
    }
}



/**
 * Handles the server requests related to updating the state of the quips,
 * and the user pending change management.
 */
class QuipUpdateComponent extends BasicSingleRequestComponent {
    final int branchId;

    final List<QuipDetails> failedCreate = [];
    final List<QuipDetails> failedUpdate = [];
    final List<QuipDetails> failedDelete = [];


    QuipUpdateComponent(ServerStatusService server, this.branchId) :
        super(server);

    @override
    void reload() {}


    Future<ServerResponse> createPendingChange() {
        return addRequestWithToken((ServerStatusService server, String csrf) =>
            server.put("/branch/${branchId}/pending", csrf,
                data: { 'changes': -1 }),
            "create_change");
    }


    Future<ServerResponse> commitChanges() {
        // TODO if there are pending changes that haven't been pushed to the
        // server, push them now.

        return addRequestWithToken((ServerStatusService server, String csrf) =>
            server.post("/branch/${branchId}/pending", csrf,
                    data: { "action": "commit" }),
            "commit_change");
    }


    Future<ServerResponse> abandonChanges() {
        return addRequestWithToken((ServerStatusService server, String csrf) =>
            server.delete("/branch/${branchId}/pending", csrf),
            "delete_change");
    }


    /**
     * User added a new quip.  Insert it into the list and save to the server.
     */
    Future<QuipDetails> newQuip(QuipDetails pendingQuip) {
        return addRequestWithToken((ServerStatusService server, String csrf) =>
                server.put('/branch/${branchId}/pending/quip', csrf,
                    data: pendingQuip.toJson()),
                "create_quip")
            .then((ServerResponse resp) {
                if (resp.wasError) {
                    failedCreate.add(pendingQuip);
                    throw new Exception("Server error: ${resp.message}");
                } else if (resp.jsonData == null) {
                    throw new Exception("Server rejected quip.");
                } else {
                    QuipDetails newQ = new QuipDetails.fromJson(
                            branchId, resp.jsonData);
                    return newQ;
                }
            });
    }


    Future<ServerResponse> updateQuip(QuipDetails quip) {
        return addRequestWithToken((ServerStatusService server, String csrf) =>
            server.post('/branch/${branchId}/pending/quip/${quip.id}', csrf,
                data: quip.toJson()),
            "update_quip")
        .then((ServerResponse resp) {
            if (resp.wasError) {
                failedUpdate.add(quip);
            }
            return resp;
        });
    }


    Future<ServerResponse> deleteQuip(QuipDetails quip) {
        return addRequestWithToken((ServerStatusService server, String csrf) =>
            server.delete('/branch/${branchId}/pending/quip/${quip.id}', csrf),
            "delete_quip")
        .then((ServerResponse resp) {
            if (resp.wasError) {
                failedDelete.add(quip);
            }
            return resp;
        });
    }


    Future<ServerResponse> massUpdates(List<QuipDetails> changed) {
        throw new UnimplementedError();
    }
}
