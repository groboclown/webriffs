
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../json/quip_details.dart';

import '../../util/paging.dart';



/**
 * A deep cache for the quips.  It pages the quips in, but keeps a longer list
 * of the quips in memory than what the server returns.  The actual control
 * over when to page, and which data to clear, is up to the owning component.
 *
 * This uses the PagingComponent to help with the loading of the data and the
 * error / loading state,
 */
class QuipPaging extends PagingComponent {

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
        super(server, path, null, false);


    @override
    Future<ServerResponse> onSuccess(Iterable data) {
        quips.clear();
        data.forEach((Map<String, dynamic> json) {
            quips.add(new QuipDetails.fromJson(branchId, json));
        });
        return null;
    }

    // TODO: implement currentPage
    @override
    int get currentPage => null;

    // TODO: implement errorMessage
    @override
    String get errorMessage => null;

    // TODO: implement filter_names
    @override
    Iterable<String> get filter_names => null;

    // TODO: implement hasError
    @override
    bool get hasError => null;

    // TODO: implement loadedFromServer
    @override
    bool get loadedFromServer => null;

    // TODO: implement pageCount
    @override
    int get pageCount => null;

    // TODO: implement recordCount
    @override
    int get recordCount => null;

    // TODO: implement recordsPerPage
    @override
    int get recordsPerPage => null;

    // TODO: implement sortOrder
    @override
    String get sortOrder => null;

    // TODO: implement sortedBy
    @override
    String get sortedBy => null;

    @override
    Future<ServerResponse> updateFromServer({int nextPage: null,
            int newRecordsPerPage: null, String newSortedBy: null,
            String newSortOrder: null, Map<String, dynamic> newFilters: null}) {
        return current.updateFromServer(
                nextPage: nextPage,
                newRecordsPerPage: newRecordsPerPage,
                newSortedBy: newSortedBy,
                newSortOrder: newSortOrder,
                newFilters: newFilters);
    }
}
