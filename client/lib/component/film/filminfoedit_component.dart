
library filminfoedit_component;

import 'dart:async';
import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../util/async_component.dart';

/**
 * The model data used by the edit component, which is passed-in from the
 * parent.  This data structure is essentially the communication point between
 * the child and parent.  This object is controlled by the child.
 * The low-level details behind the error status are
 * hidden from the parent.
 *
 * The parent can communicate to the form to indicate that it's committing
 * the changes by setting the "commit" flag to true.  If this is enabled,
 * the edit component won't allow updates.
 */
class FilmInfo {
    bool hasError = true;
    bool checking = false;
    bool commit = false;
    String filmName;
    int releaseYear;
}


/**
 * The bits of the film top-level info that need validation against the server.
 * Specifically, the film name and the release year.
 */
@Component(
    selector: 'filminfo-edit',
    templateUrl: 'filminfoedit_component.html'
    //cssUrl: 'filminfoedit_component.css'
    )
class FilmInfoEditComponent extends SingleRequestComponent {
    final ServerStatusService _server;

    AsyncComponent get cmp => this;

    @NgOneWayOneTime('film-info')
    FilmInfo filmInfo;

    FilmInfoEditComponent(ServerStatusService server) :
        _server = server,
        super(server);

    bool get enabled => ! filmInfo.commit;

    bool get disabled => filmInfo.commit;

    bool _filmInUse = false;

    bool get filmInUse => _filmInUse;

    bool _isYearValid = true;

    bool get isYearValid => _isYearValid;

    bool _isNameValid = true;

    bool get isNameValid => _isNameValid;

    bool get hasError => filmInfo.hasError;

    bool get isChecking => filmInfo.checking;

    String get filmName => filmInfo.filmName;

    int get releaseYear => filmInfo.releaseYear;

    set filmName(String name) {
        if (filmInfo.filmName != name) {
            validateFilmNameYear(name, _checkYear(filmInfo.releaseYear));
        }
    }


    int _checkYear(dynamic year) {
        if (year == null) {
            _isYearValid = false;
            filmInfo.hasError = true;
            return null;
        } else {
            // convert the year here, so that the check for did-change is valid.
            try {
                return int.parse(year.toString());
            } catch (FormatException) {
                _isYearValid = false;
                filmInfo.hasError = true;
                return null;
            }
        }
    }

    set releaseYear(dynamic year) {
        int yearInt = _checkYear(year);
        if (yearInt != null && yearInt != filmInfo.releaseYear) {
            validateFilmNameYear(filmInfo.filmName, year);
        }
    }


    @override
    void reload() {
        validateFilmNameYear(filmInfo.filmName, filmInfo.releaseYear);
    }



    void validateFilmNameYear(String name, int year) {
        // Completely set the error state flags, and possibly load data
        // from the server if things on this side look fine.

        if (year != null && year >= 1800 && year < 9999) {
            _isYearValid = true;
        } else {
            _isYearValid = false;
            filmInfo.hasError = true;
        }

        if (name == null || ! (name is String)) {
            name = name.toString();
            _isNameValid = false;
        } else if (name.length > 0 && name.length < 200) {
            _isNameValid = true;
        } else {
            _isNameValid = false;
        }

        filmInfo.releaseYear = year;
        filmInfo.filmName = name;

        if (_isNameValid && _isYearValid) {
            _checkIfFilmNameAndYearInUse();
        } else {
            filmInfo.hasError = true;
        }

    }



    void _checkIfFilmNameAndYearInUse() {
        // we're going to call out to the server to see if the film is in use.
        // for safety, we'll state that there's an issue with the film info,
        // but we won't mark the film as being in use.
        filmInfo.hasError = false;
        filmInfo.checking = true;
        _filmInUse = false;

        String year = filmInfo.releaseYear.toString();
        String path = "/filmexists?Name=" +
                Uri.encodeQueryComponent(filmInfo.filmName) +
                "&Release_Year=${year}";
        get(path);
    }


    @override
    Future<ServerResponse> onSuccess(ServerResponse response) {
        filmInfo.checking = false;
        if (response.jsonData != null &&
                response.jsonData.containsKey('exists')) {
            _filmInUse = response.jsonData['exists'] == true;
        } else {
            _filmInUse = false;
        }
        filmInfo.hasError = _filmInUse;
        return null;
    }


    @override
    void onError(Exception e) {
        filmInfo.checking = false;
        filmInfo.hasError = true;
        super.onError(e);
    }

}


