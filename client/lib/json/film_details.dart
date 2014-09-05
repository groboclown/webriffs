library film_details;

import 'dart:async';

import '../service/server.dart';


class LinkRecord {
    final int filmId;
    final String urlPrefix;
    final String name;
    final String desc;
    String serverUri;
    String errorUri;
    String uri;
    String error;
    bool get hasError => uri == errorUri && error != null;

    bool get isChanged => uri != serverUri;
    bool get isUnchanged => uri == serverUri;

    String get url => uri == null ? null : urlPrefix + uri;
    bool get isDefined => url != null;


    factory LinkRecord.fromJson(Map<String, dynamic> row) {
        return new LinkRecord(row['Film_Id'], row['Url_Prefix'],
                row['Name'], row['Description'], row['Uri']);
    }


    LinkRecord(this.filmId, this.urlPrefix,
           this.name, this.desc, this.serverUri) {
        this.uri = serverUri;
    }


    Map<String, dynamic> toJson() {
        Map<String, dynamic> ret = {};
        ret['Uri'] = uri;
        return ret;
    }


    void cancel() {
        this.uri = serverUri;
    }

    Future<ServerResponse> save(ServerStatusService server) {
        return server.createCsrfToken('save_film_link').then(
                (String csrfToken) {
            final String submittedUri = uri;
            Map<String, dynamic> data = toJson();
            return server.post('film/' + filmId.toString() + '/link/' + name,
                    csrfToken, data: data)
                .then((ServerResponse resp) {
                    if (resp.wasError) {
                        error = resp.message;
                        errorUri = submittedUri;
                    } else {
                        error = null;
                        serverUri = submittedUri;
                        errorUri = null;
                    }
                });
        });
    }
}
