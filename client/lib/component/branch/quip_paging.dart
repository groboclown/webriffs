
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';



class Quip {
    int _quipId;
    int _branchId;
    final List<QuipTag> _tags = [];
    String _text;
    int _timestamp;
    bool _changed = false;

    String get text => _text;
    int get timestamp => _timestamp;
    bool get changed => _changed;

    set text(String t) {
        if (t != _text) {
            _changed = true;
            _text = t;
        }
    }

    set timestamp(int t) {
        if (t != _timestamp) {
            _changed = true;
            _timestamp = t;
        }
    }

    void addTag(String tag) {
        if (tag != null) {
            tag = tag.trim();
            for (QuipTag t in _tags) {
                if (t.name.toLowerCase() == tag.toLowerCase()) {
                    return;
                }
            }
            _tags.add(new QuipTag(tag));
            _changed = true;
        }
    }

    void removeTag(QuipTag t) {
        _changed |= _tags.remove(t);
    }
}



class QuipTag {
    final String name;

    QuipTag(this.name);
}



/**
 * A deep cache for the quips.  It pages the quips in, but keeps a longer list
 * of the quips in memory than what the server returns.  The actual control
 * over when to page, and which data to clear, is up to the owning component.
 */
class QuipPaging extends PagingComponent {
    final int branchId;
    final int changeId;
    final List<Quip> quips;

    factory QuipPaging(ServerStatusService server, int branchId, int changeId) {
        String path = "/branch/${branchId}/quips";

        // FIXME
    }


    QuipPaging._(ServerStatusService server, String path) :
        super(server, path, null, false);

}