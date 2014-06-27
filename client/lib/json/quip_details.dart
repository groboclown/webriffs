library quip_details;


class QuipDetails {
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

    QuipDetails(this._branchId, this._quipId);

    factory QuipDetails.fromJson(int branchId, Map<String, dynamic> json) {
        int quipId = json['Gv_Item_Id'];
        int versionId = json['Gv_Item_Version_Id'];
        QuipDetails q = new QuipDetails(branchId, quipId);
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
