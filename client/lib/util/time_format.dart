/**
 * Some formatters for time related functions.
 *
 * It also handles the NTSC / PAL / embedded dilema.
 */
library time_format;


// FIXME move the stopwatch display and parse over here.


class TimeDialation {
    // FIXME add default implementations for NTSC and PAL

    static const NATIVE = const TimeDialation(1.0);

    /**
     * Converts from the real (original format) time to the
     * time as the user should be seeing it.
     */
    final double actualToDisplayRatio;

    const TimeDialation(this.actualToDisplayRatio);

    double toActual(double display) {
        return display / actualToDisplayRatio;
    }

    double toDisplay(double actual) {
        return actual * actualToDisplayRatio;
    }
}


class TimeDisplayEdit {
    TimeDialation _timeDialation;

    // FIXME finish up.
}
