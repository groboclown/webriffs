
library viewfilm_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../service/server.dart';
import '../service/user.dart';
import 'filminfoedit_component.dart';

import '../util/async_component.dart';

// FIXME move the branch and tag common stuff into its own file.
import 'filmlist_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'view-film',
    templateUrl: 'packages/webriffs_client/component/viewfilm_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class ViewFilmComponent extends PagingComponent {
    final ServerStatusService _server;
    final UserService _user;
    final int _filmId;
    final String _inputFilmId;
    final FilmInfo filmInfo = new FilmInfo();

    bool _validFilmId;
    String name;
    int releaseYear;
    String createdOn;
    String lastUpdatedOn;
    bool _detailsLoaded;

    bool get filmInfoDisabled => filmInfo.hasError || filmInfo.checking ||
            filmInfo.commit;
    bool isEditing = false;

    bool get detailsLoaded => _detailsLoaded;
    bool get validFilm => _validFilmId;
    String get inputFilmId => _inputFilmId;

    bool get canEdit => _user.canEditFilms;
    bool get cannotEdit => ! canEdit && _user.loggedIn;
    bool get notLoggedIn => ! _user.loggedIn;
    bool get canCreateBranch => _user.canCreateBranch;

    final List<BranchRecord> branches = [];
    final List<LinkRecord> links = [];

    bool get noBranches => loadedSuccessful && branches.isEmpty;


    factory ViewFilmComponent(ServerStatusService server, UserService user,
            RouteProvider routeProvider) {
        String inputFilmId = null;
        bool validFilmId = false;
        int filmId = 0;
        // Error checking for the film id
        if (routeProvider.parameters.containsKey('filmId')) {
            inputFilmId = routeProvider.parameters['filmId'];
            try {
                filmId = int.parse(inputFilmId);
                validFilmId = true;
            } catch (e) {
                validFilmId = false;
            }
        }
        String path = "/film/" + filmId.toString() + "/branch";
        return new ViewFilmComponent._(server, user, path, inputFilmId,
                filmId, validFilmId);
    }


    ViewFilmComponent._(ServerStatusService server, this._user, String path,
            this._inputFilmId, this._filmId, this._validFilmId) :
            _server = server,
            super(server, path) {
        _detailsLoaded = false;
        if (_validFilmId) {
            loadDetails().then((_) {
                if (_validFilmId) {
                    update();
                }
            });
        }
    }




    Future<ServerResponse> loadDetails() {
        _detailsLoaded = false;
        return _server.get('/film/' + _filmId.toString(), null).
            then((ServerResponse resp) {
                if (resp.wasError) {
                    _validFilmId = false;
                } else {
                    int projectId = resp.jsonData['Gv_Project_Id'];
                    name = resp.jsonData['Name'];
                    releaseYear = resp.jsonData['Release_Year'];
                    createdOn = resp.jsonData['Created_On']; // datetime -> ?
                    lastUpdatedOn = resp.jsonData['Last_Updated_On']; // datetime -> ?
                    _detailsLoaded = true;

                    links.clear();
                    List<Map<String, dynamic>> jsonLinks = resp.jsonData['links'];
                    jsonLinks.forEach((Map<String, dynamic> row) {
                        links.add(new LinkRecord.fromJson(row));
                    });
                }
            });
    }


    Future<ServerResponse> onSuccess(Iterable<dynamic> data) {
        branches.clear();
        data.forEach((Map<String, dynamic> row) {
            branches.add(new BranchRecord.fromJson(_server, _filmId, row));
        });
        return null;
    }


    Future<ServerResponse> updateFilm() {
        // FIXME

        isEditing = false;
    }


    void revert() {
        // FIXME revert the fields
        isEditing = false;
    }




    Future<ServerResponse> saveLinks() {
        // FIXME
    }

}



class LinkRecord {
    final int filmId;
    final int linkTypeId;
    final String urlPrefix;
    final String name;
    final String desc;
    String uri;

    String get url => uri == null ? null : urlPrefix + uri;
    bool get isDefined => url != null;


    factory LinkRecord.fromJson(Map<String, dynamic> row) {
        return new LinkRecord(row['Film_Id'], row['Link_Type_Id'],
                row['Url_Prefix'], row['Name'], row['Description'],
                row['Uri']);
    }


    LinkRecord(this.filmId, this.linkTypeId, this.urlPrefix, this.name,
                       this.desc, this.uri);
}
