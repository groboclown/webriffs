library quip_details;


class QuipDetails {
    static final int MAX_TAG_COUNT = 20;

    final int _quipId;
    final int _branchId;
    final List<QuipTag> _tags = [];
    String _text;
    int _timestamp;
    int _committedTimestamp = null;
    bool _changed = false;


    int get id => _quipId;
    String get text => _text;
    int get timestamp => _timestamp;
    int get committedTimestamp => _committedTimestamp;
    bool get changed => _changed;

    List<QuipTag> get tags => _tags;

    QuipDetails(this._branchId, this._quipId);

    QuipDetails.pending() :
        _branchId = null,
        _quipId = null;

    factory QuipDetails.fromJson(int branchId, Map<String, dynamic> json) {
        int quipId = json['Gv_Item_Id'];
        int versionId = json['Gv_Item_Version_Id'];
        QuipDetails q = new QuipDetails(branchId, quipId);
        q._text = json['Text_Value'];
        q._timestamp = q._committedTimestamp = json['Timestamp_Millis'];

        for (int i = 1; i <= MAX_TAG_COUNT; ++i) {
            String t = json['TAG_' + i.toString()];
            if (t != null) {
                t = t.trim();
                q._tags.add(new QuipTag(t));
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
        if (t != _committedTimestamp) {
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

    /**
     * Marks the quip as committed, and returns a JSon version of the
     * object.
     */
    Map<String, dynamic> toJson() {
        if (! changed) {
            return null;
        }
        var ret = <String, dynamic>{};
        ret['Gv_Item_Id'] = _quipId;
        ret['Text_Value'] = _text;
        ret['Timestamp_Millis'] = _timestamp;
        for (int i = 0; i < _tags.length; ++i) {
            ret['TAG_' + (i + 1).toString()] = _tags[i].name;
        }

        _changed = true;
        _committedTimestamp = _timestamp;

        return ret;
    }

}



class QuipTag {
    final String name;

    QuipTag(this.name);
}
