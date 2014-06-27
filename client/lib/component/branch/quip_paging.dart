
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../json/quip_details.dart';




/**
 * A deep cache for the quips.  It pages the quips in, but keeps a longer list
 * of the quips in memory than what the server returns.  The actual control
 * over when to page, and which data to clear, is up to the owning component.
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
}
