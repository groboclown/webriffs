/**
 * Some formatters for time related functions.
 *
 * It also handles the NTSC / PAL / embedded dilema.
 */
library time_format;


class TimeFormatException implements Exception {
    TimeFormatException();

    @override
    String toString() {
        return "TimeFormatException";
    }
}


// FIXME move the stopwatch display and parse over here.


class TimeDialation {
    // FIXME add default implementations for NTSC and PAL

    static const NATIVE = const TimeDialation("native (1:1)", 1.0);

    static const String NULL_DISPLAYED_TIME = "0:00:00.00";


    final String name;

    /**
     * Converts from the real (original format) time to the
     * time as the user should be seeing it (in seconds).
     */
    final double actualToDisplayRatio;

    const TimeDialation(this.name, this.actualToDisplayRatio);

    /**
     * Converts from the displayed time to the actual (server) time.
     */
    double toActual(double display) {
        return display / actualToDisplayRatio;
    }

    /**
     * Converts from the actual (server) time to the displayed time.
     */
    double toDisplay(double actual) {
        return actual * actualToDisplayRatio;
    }


    /**
     * Returns the actual (server) time as a display value in proper
     * form.
     */
    String displayString(double actual) {
        return _toTimeString(toDisplay(actual));
    }


    /**
     * Parses the time field text into a the given time value, converted from
     * the displayed time to the actual time, in seconds.
     *
     * @return the time in seconds, without translating to or from the
     *      display value.
     * @throws TimeFormatException
     */
    double parseDisplay(String timeField) {
        return toDisplay(_parse(timeField));
    }


    String _toTimeString(double time) {
        if (time == null) {
            return NULL_DISPLAYED_TIME;
        }

        int millis = (time * 1000.0).toInt();
        int c = (millis ~/ 10);
        // centi-seconds
        var t1 = (c % 100).toString();
        if (t1.length < 2) {
            t1 = "0" + t1;
        }

        // seconds
        c = (c ~/ 100);
        var t2 = (c % 60).toString();
        if (t2.length < 2) {
            t2 = "0" + t2;
        }

        // minutes
        c = (c ~/ 60);
        var t3 = (c % 60).toString();
        if (t3.length < 2) {
            t3 = "0" + t3;
        }

        // hours
        c = (c ~/ 60);
        return c.toString() + ":" + t3 + ":" + t2 + "." + t1;
    }


    /**
     * Parses the time field text into a the given time value.
     *
     * @return the time in seconds, without translating to or from the
     *      display value.
     * @throws TimeFormatException
     */
    double _parse(String timeField) {
        RegExp exp = new RegExp(
            r"^\s*(?:(\d+)\s*:\s*)?(?:(\d+)\s*:\s*)?(\d+|\.\d+|\d+\.\d+|\d+\.)\s*$");
        List<Match> matches = new List.from(exp.allMatches(timeField));

        if (matches.length != 1) {
            throw new TimeFormatException();
        }

        Match match = matches[0];
        //_log.info("Matched on [" + match[0] + "]");

        int hours = 0;
        int minutes = 0;
        double seconds = 0.0;

        /* DEBUG
        String s = "";
        for (int i = 1; i <= match.groupCount; ++i) {
            s += " ${i} = ${match[i]};";
        }
        _log.info("Groups:${s}");
        */
        if (match[3] == null) {
            throw new TimeFormatException();
                //"Unexpected regex state: [" + match[0] + "]");
        }
        seconds = double.parse(match[3]);
        if (match[1] != null) {
            if (match[2] != null) {
                // both hour and minute set
                hours = int.parse(match[1]);
                minutes = int.parse(match[2]);
            } else {
                // only minute set
                minutes = int.parse(match[1]);
            }
        } else if (match[2] != null) {
            throw new TimeFormatException();
            //    throw new Exception("Unexpected regex state: [" +
            //            match[0] + "]");
        }

        int millis = (hours * 60 * 60 * 1000) +
                (minutes * 60 * 1000) +
                (seconds * 1000).toInt();

        return millis / 1000.0;
    }
}


class TimeDisplayEdit {
    TimeDialation _timeDialation = TimeDialation.NATIVE;

    TimeDialation get dialation => _timeDialation;

    set dialation(TimeDialation td) {
        if (td == null) {
            throw new Exception("null arg");
        }
        _timeDialation = td;
        _timeField = td.displayString(_actualTime);
    }

    double _actualTime = 0.0;
    double get actualSeconds => _actualTime;

    String _timeField = TimeDialation.NULL_DISPLAYED_TIME;
    String get timeField => _timeField;
    bool formatError = false;

    set actualSeconds(double actual) {
        _actualTime = actual;
        _timeField = _timeDialation.displayString(actual);
    }

    set timeField(String str) {
        try {
            double time = _timeDialation.parseDisplay(str);
            formatError = false;
            _actualTime = time;
        } catch (e) {
            formatError = true;
        }
    }

}
