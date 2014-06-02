
library createfilm_component;


import 'package:angular/angular.dart';

import '../service/server.dart';
import '../service/user.dart';

/**
 * The UI component view of the list of films.  This model stores the
 * current information about what the user is entering into the UI.
 */
@Component(
    selector: 'create-film',
    templateUrl: 'packages/webriffs_client/component/createfilm_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class CreateFilmComponent {
    final ServerStatusService _server;

    final UserService _user;

    CreateFilmComponent(this._server, this._user);

    String _filmName;

    int _releaseYear = null;

    bool filmInUse = false;

    String get filmName => _filmName;

    int get releaseYear => _releaseYear;

    bool get isLoggedIn => _user.loggedIn;

    bool get notLoggedIn => ! _user.loggedIn;

    set filmName(String name) {
        if (_filmName != name) {
            _filmName = name;
            checkIfFilmNameAndYearInUse(name, _releaseYear);
        }
    }


    set releaseYear(int year) {
        if (_releaseYear != year) {
            _releaseYear = year;
            checkIfFilmNameAndYearInUse(_filmName, year);
        }
    }


    void checkIfFilmNameAndYearInUse(final String name, final int year) {
        filmInUse = false;
        if (name == null && year == null) {
            // Nothing to do
            return;
        }

        String path = "/film?";

        if (name != null) {
            path += "name=" + Uri.encodeQueryComponent(name);
            if (year != null) {
                path += '&';
            }
        }
        if (year != null) {
            path += "yearMin=${year}&yearMax=${year}";
        }

        // Assume the name is not in use.  This is safer, as it allows the
        // server to be the final decider if the name is used or not.
        _server.get(path, null).
            then((ServerResponse response) {
                if (_filmName == name && _releaseYear == year &&
                        response.jsonData != null &&
                        response.jsonData.containsKey('result')) {
                    dynamic data = response.jsonData['result'];
                    filmInUse = (data is Iterable) && (data.isNotEmpty);
                } else {
                    filmInUse = false;
                }
            });
    }
}


