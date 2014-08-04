
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../json/quip_details.dart';



/**
 * A deep cache for the quips.  It pages the quips in, but keeps a longer list
 * of the quips in memory than what the server returns.  The actual control
 * over when to page, and which data to clear, is up to the owning component.
 *
 * This uses the PagingComponent to help with the loading of the data and the
 * error / loading state.
 */
class QuipPaging extends PagingComponent {
    final ServerStatusService _server;
    int _previousBuffer = 50;
    int _nextBuffer = 50;

    int get previousBuffer => _previousBuffer;

    int get nextBuffer => _nextBuffer;

    // FIXME need a better storage for the quips.  They need to be stored such
    // that N items are saved to reduce paging from the server.  When a get
    // request is made for an item that is "near" the end of the current
    // cache (in either direction), a request should be made to pull in the
    // next ones.

    // The forward/backwards cache should be configurable depending if the user
    // is in edit or view or playback mode.

    final int branchId;
    final int changeId;
    final List<QuipDetails> quips;

    factory QuipPaging(ServerStatusService server, int branchId, int changeId) {
        String path = "/branch/${branchId}/version/${changeId}/quip";

        return new QuipPaging._(server, branchId, changeId, path);
    }

    /**
     * Fetch the quips that the user has pending a commit.
     */
    factory QuipPaging.pending(ServerStatusService server, int branchId) {
        String path = "/branch/${branchId}/pending/quip";

        return new QuipPaging._(server, branchId, null, path);
    }


    QuipPaging._(ServerStatusService server, this.branchId, this.changeId,
            String path) :
        quips = [],
        _server = server,
        super(server, path, null, false);


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
    }

    void _updatedTime(QuipDetails quip) {
        // update the location of the quip in the list.
        // Should perform a binary search for the current location of this
        // quip, followed by another one to find where to insert it.

        // For now, we'll do the simple route.
        quips.remove(quip);
        _mergeQuips([ quip ]);
    }

    /**
     * FIXME This has an issue where it can't remove quips if the user is
     * looking at the head revision.  This can be solved by *forcing* the
     * user to always look at a specific version.
     */
    @override
    Future<ServerResponse> onSuccess(Iterable data) {
        // The incoming quips should all be sorted already.
        var newQuips = <QuipDetails>[];
        data.forEach((Map<String, dynamic> json) {
            newQuips.add(new QuipDetails.fromJson(branchId, json));
        });

        _mergeQuips(newQuips);

        return null;
    }


    /**
     * Merges a list of (sorted by timestamp) quips into the existing
     * list.
     */
    void _mergeQuips(List<QuipDetails> newQuips) {
        // These quips should be in the middle of the current list,
        // so a merge sort should be most appropriate.  The efficient method
        // would be to check the size of the incoming quips, and estimate the
        // pre/post sizes that should remain.

        // For now, though, we'll just save everything by merging the two
        // sorted lists.

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
