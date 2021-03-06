
library createfilm_component;

import 'dart:async';
import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';
import '../../util/async_component.dart';
import 'filminfoedit_component.dart';


/**
 * The UI component view of the list of films.  This model stores the
 * current information about what the user is entering into the UI.
 *
 * Note that the setters can be called on "submit" by Angular even if the
 * value wasn't directly set.  Therefore, they need proper protection against
 * setting to the current value.
 */
@Component(
    selector: 'create-film',
    templateUrl: 'packages/webriffs_client/component/film/createfilm_component.html'
    //cssUrl: 'errorstatus_component.css'
    )
class CreateFilmComponent extends RequestHandlingComponent {
    final ServerStatusService _server;

    final Router _route;

    final UserService _user;

    CreateFilmComponent(this._server, this._user, this._route);

    final FilmInfo filmInfo = new FilmInfo();

    bool get isValid => ! filmInfo.hasError && ! filmInfo.checking;

    bool get disabled => filmInfo.commit || ! isValid;

    String get filmName => filmInfo.filmName;

    int get releaseYear => filmInfo.releaseYear;

    bool get isLoggedIn => _user.loggedIn;

    bool get notLoggedIn => ! _user.loggedIn;

    void createFilm() {
        if (isValid) {
            filmInfo.commit = true;
            csrfRequest(_server, 'create_film',
                    (ServerStatusService server, String token) {
                return _server.put('/film', token, data: {
                            'Name': filmInfo.filmName,
                            'Release_Year': filmInfo.releaseYear
                    });
            });
        } else {
            print("current film is not valid.");
        }
    }


    @override
    Future<ServerResponse> onSuccess(ServerResponse response) {
        // Reset the fields
        filmInfo.hasError = true;

        if (response != null) {
            filmInfo.filmName = null;
            filmInfo.releaseYear = null;

            int filmId = response.jsonData['Film_Id'];
            int branchId = response.jsonData['Branch_Id'];
            int changeId = response.jsonData['Change_Id'];

            // redirect to the edit page
            _route.go('Branch Playback', {
                'branchId': branchId
            });
        } else {
            // the commit failed
            filmInfo.commit = false;
        }
        return null;
    }


    @override
    void reload() {
        // do nothing.  Puts do not reload.
    }
}


