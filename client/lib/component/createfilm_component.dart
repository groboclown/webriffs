
library createfilm_component;


import 'package:angular/angular.dart';

import '../service/server.dart';
import '../service/user.dart';

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
    templateUrl: 'packages/webriffs_client/component/createfilm_component.html',
    //cssUrl: 'packages/webriffs_client/component/errorstatus_component.css',
    publishAs: 'cmp')
class CreateFilmComponent {
    final ServerStatusService _server;

    final Router _route;

    final UserService _user;

    CreateFilmComponent(this._server, this._user, this._route);

    String _filmName;

    int _releaseYear = null;

    bool filmInUse = false;

    String errorMessage = "Must define a film name and release year";

    bool get hasError => errorMessage != null;

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

    set releaseYear(year) {
        if (year is String) {
            try {
                year = int.parse(year);
            } catch (FormatException) {
                errorMessage = "Year must be a number";
                return;
            }
        } else if (! (year is int)) {
            errorMessage = "Invalid year format";
            return;
        }
        if (year == _releaseYear) {
            return;
        }
        if (year >= 1800 && year < 9999) {
            _releaseYear = year;
            checkIfFilmNameAndYearInUse(_filmName, year);
        } else {
            if (_filmName == null) {
                // FIXME common place for errors
                errorMessage = "Must define a film name and release year";
            } else {
                // FIXME Extra debugging info
                errorMessage = "Must define a valid release year (current year: [${_releaseYear}], input year: [${year}]) ${year.runtimeType}";
            }
        }
    }


    void checkIfFilmNameAndYearInUse(final String name, final int year) {
        filmInUse = false;
        if (name == null && year == null) {
            // Nothing to do
            errorMessage = "Must define a film name and release year";
            return;
        }
        errorMessage = null;

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
                if (filmInUse) {
                    errorMessage =
                        "A film with that name and year already exists in the system";
                }
            });
    }


    void createFilm() {
        if (_filmName != null && _releaseYear != null) {
            // FIXME notify that the form is being submitted.
            // This will do for now...
            errorMessage = "Creating new film...";

            _server.createCsrfToken('create_film').then((String token) {
                errorMessage = "Completing the creation...";
                return _server.put('/film', token, data: {
                            'name': _filmName,
                            'year': _releaseYear
                    }).
                    then((ServerResponse response) {
                        // Reset the fields
                        _filmName = null;
                        _releaseYear = null;
                        errorMessage = "Must define a film name and release year";
                        if (response == null) {
                            errorMessage = "Unknown server connection problem.";
                            return;
                        }
                        int filmId = response.jsonData['film_id'];
                        int branchId = response.jsonData['branch_id'];
                        int changeId = response.jsonData['change_id'];

                        // redirect to the edit page
                        _route.go('Edit <branchId>', {
                            'branchId': branchId,
                            'changeId': changeId
                        });
                    }).
                    catchError((Exception e) {
                        errorMessage = null;
                    });

            });
        }
    }
}


