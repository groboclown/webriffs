
library viewfilm_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import 'filminfoedit_component.dart';

import '../../util/async_component.dart';

import '../../json/film_details.dart';
// FIXME switch this to using the film_details import.
import 'filmlist_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'view-film',
    templateUrl: 'packages/webriffs_client/component/film/viewfilm_component.html'
    //cssUrl: 'viewfilm_component.css',
    )
class ViewFilmComponent extends PagingComponent {
    final ServerStatusService _server;
    final UserService _user;
    final int filmId;
    final String _inputFilmId;
    final FilmInfo filmInfo = new FilmInfo();

    AsyncComponent get cmp => this;

    bool _validFilmId;
    String name;
    int releaseYear;
    String createdOn;
    String lastUpdatedOn;
    bool _detailsLoaded;

    bool get filmInfoDisabled => filmInfo.hasError || filmInfo.checking ||
            filmInfo.commit;
    bool _isEditing = false;

    bool get isEditing => _isEditing;

    bool get detailsLoaded => _detailsLoaded;
    bool get validFilm => _validFilmId;
    String get inputFilmId => _inputFilmId;

    bool get canEdit => _user.canEditFilms;
    bool get cannotEdit => ! canEdit && _user.loggedIn;
    bool get notLoggedIn => ! _user.loggedIn;

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
            this._inputFilmId, this.filmId, this._validFilmId) :
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



    void edit() {
        if (! _isEditing) {
            filmInfo.filmName = name;
            filmInfo.releaseYear = releaseYear;
            _isEditing = true;
        }
    }




    Future<ServerResponse> loadDetails() {
        _detailsLoaded = false;
        return _server.get('/film/' + filmId.toString(), null).
            then((ServerResponse resp) {
                if (resp.wasError) {
                    _validFilmId = false;
                } else {
                    _loadFilmJsonResponse(resp);
                }
            });
    }


    void _loadFilmJsonResponse(ServerResponse resp) {
        int projectId = resp.jsonData['Gv_Project_Id'];
        name = resp.jsonData['Name'];
        dynamic year = resp.jsonData['Release_Year'];
        if (year is String) {
            releaseYear = int.parse(year);
        } else if (! (year is int)) {
            print("Invalid year from server: " + year);
            releaseYear = null;
        }
        createdOn = resp.jsonData['Created_On']; // datetime -> ?
        lastUpdatedOn = resp.jsonData['Last_Updated_On']; // datetime -> ?
        _detailsLoaded = true;

        links.clear();
        List<Map<String, dynamic>> jsonLinks = resp.jsonData['links'];
        jsonLinks.forEach((Map<String, dynamic> row) {
            links.add(new LinkRecord.fromJson(row));
        });
    }


    Future<ServerResponse> onSuccess(Iterable<dynamic> data) {
        branches.clear();
        data.forEach((Map<String, dynamic> row) {
            branches.add(new BranchRecord.fromJson(_server, filmId, row));
        });
        return null;
    }


    Future<ServerResponse> updateFilm() {
        if (filmInfo.hasError) {
            // the same as canceling
            revert();
            return null;
        }
        var ret = _server.createCsrfToken('update_film').then((String csrf) {
            Map<String, dynamic> jsonData = {
                'Name': filmInfo.filmName,
                'Release_Year': filmInfo.releaseYear
            };
            return _server.post('/film/' + filmId.toString(), csrf,
                    data: jsonData);
        }).then((ServerResponse response) {
            if (response.wasError) {
                // FIXME report error from server better.
            } else {
                _loadFilmJsonResponse(response);
            }
        });

        _isEditing = false;

        return ret;
    }


    Future<ServerResponse> saveLink(LinkRecord link) {
        return link.save(_server);
    }


    void revert() {
        _isEditing = false;
    }
}

