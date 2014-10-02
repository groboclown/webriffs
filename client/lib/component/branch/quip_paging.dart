
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


    QuipPaging._(this._server, this.branchId, this.changeId) {
        reload();

    }


    /**
     * User added a new quip.  Insert it into the list and save to the server.
     */
    void newQuip(QuipDetails pendingQuip) {
        final String path = '/branch/${branchId}/pending/quip';
        _server.createCsrfToken('create_quip').then((String csrfToken) {
            // FIXME retrieve the ID for the just-added quip, and insert
            // it into the passed-in object.
            _server.put(path, csrfToken, data: pendingQuip.toJson());
        });
    }


    void deleteQuip(QuipDetails quip) {
        if (quip.id == null && quip.changed) {
            // There's a potential issue here with this logic.  There's the
            // situation where a save could be in-transit for a new object,
            // and the user clicks on delete for it.
            // FIXME handle this situation!
            quips.remove(quip);
        } else {
            // FIXME
            throw new Exception("not completed yet");
        }
    }


    /**
     * Save the existing quip to the server.
     */
    void updateQuip(QuipDetails quip) {
        if (quip.committedTimestamp != quip.timestamp) {
            _updatedTime(quip);
        }

        // FIXME
        throw new Exception("not completed yet");
    }


    void _updatedTime(QuipDetails quip) {
        // update the location of the quip in the list.
        // Should perform a binary search for the current location of this
        // quip, followed by another one to find where to insert it.

        // For now, we'll do the simple route.
        quips.remove(quip);
        _mergeQuips([ quip ]);
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
                        data.map((dynamic jsonData) =>
                            new QuipDetails.fromJson(branchId, jsonData)));
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
        while (origPos < quips.length && newPos < newQuips.length) {
            if (origPos >= quips.length) {
                quips.add(newQuips[newPos++]);
                origPos++;
            } else if (newPos >= newQuips.length) {
                // finished processing the new quips
                origPos = quips.length;
            } else if (quips[origPos].id == newQuips[newPos].id) {
                quips[origPos++] = newQuips[newPos++];
            } else if (quips[origPos].timestamp < newQuips[newPos].timestamp) {
                origPos++;
            } else { // if (quips[origPos].timestamp >= newQuips[newPos].timestamp) {
                quips.insert(origPos++, newQuips[newPos++]);
            }
        }

    }
}


typedef void QuipDisplayHandler(QuipDetails quip);


/**
 * An extension to the [BaseMediaAlertController] that includes tight
 * integration with the [QuipPaging] class to reduce the overhead of two lists
 * of quips.
 */
class QuipMediaAlertController extends BaseMediaAlertController {
    // FIXME tight integration with the QuipPaging to be memory efficient
    // when displaying the quips.

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
        while (nextIndex > 0 && _quips[nextIndex].timestamp > time) {
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
            _handler(_quips[nextIndex]);
            nextIndex++;
        }
    }
}


