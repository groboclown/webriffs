
library quip_paging;

import 'dart:async';

import '../../service/server.dart';
import '../../util/async_component.dart';



class Quip {
    static final int MAX_TAG_COUNT = 20;

    final int _quipId;
    final int _branchId;
    final List<QuipTag> _tags = [];
    String _text;
    int _timestamp;
    bool _changed = false;

    String get text => _text;
    int get timestamp => _timestamp;
    bool get changed => _changed;

    List<QuipTag> get tags => _tags;

    Quip(this._branchId, this._quipId);

    factory Quip.fromJson(int branchId, Map<String, dynamic> json) {
        int quipId = json['Gv_Item_Id'];
        int versionId = json['Gv_Item_Version_Id'];
        Quip q = new Quip(branchId, quipId);
        q._text = json['Text_Value'];
        q._timestamp = json['Timestamp_Millis'];

        for (int i = 1; i <= MAX_TAG_COUNT; ++i) {
            String t = json['TAG_' + i.toString()];
            if (t != null) {
                t = t.trim();
                _tags.add(new QuipTag(t));
            }
        }
    }

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
        _changed = _changed || _tags.remove(t);
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
            quips.add(new Quip.fromJson(branchId, json));
        });
        return null;
    }
}
