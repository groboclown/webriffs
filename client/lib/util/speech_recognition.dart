library speech_recognition;

import 'dart:async';
import 'dart:js';



/**
 * A high level controller for managing voice input into a single input.
 * The input is split into phrases that represent a bit of voice that the
 * user gave.  These phrases can then be edited as desired.
 *
 * This API is still being tested - most of this should be just something
 * that the browser handles.
 */
class SpeechController {

}



/**
 * Represents a single phrase that the user spoke.  This can be then edited
 * or removed as necessary.
 */
class SpeechPhrase {
    static int _phraseCount = 0;
    int _index;
    int get index => _index;
    SpeechResult _source;
    SpeechResult get source => _source;
    final List<SpeechResult> choices = [];
    String selection;
    bool get isFinal => source.isFinal;
    bool isEditing = false;
    bool isDeleted = false;

    SpeechPhrase(SpeechResult s) {
        _index = _phraseCount++;
        //reviseWith(s);
    }

}



LowSpeechService createSpeechService() {
    // For now, ignore the native Dart support because that's Chrome only.
    //if (SpeechRecognition.supported) {

    for (String nativeName in NATIVE_SPEECH_API_NAME) {
        if (context.hasProperty(nativeName)) {
            try {
                JsFunction func = context[nativeName];
                JsObject obj = new JsObject(func, []);
print("++++++++ using speech ${nativeName}");
                return new JsLowSpeechService(obj);
            } catch (e, stack) {
print("++++++++ native speech API ${nativeName} exists, but cannot be created.");
print(e.toString());
print(stack.toString());
                // keep going
            }
        }
    }

    // Browser doesn't support a speech recognition API.
print("No speech API supported");
    return null;
}


/**
 * Low-level speech API.
 */
abstract class LowSpeechService {
    bool get isCapturing;

    Stream get startEvents;

    /**
     * Errors in the speech service are posted as errors in this stream.
     * Error objects will be of type [SpeechError].
     */
    Stream<SpeechResult> get resultEvents;

    String get lang;
    set lang(String lg);

    List<String> get supportedLanguages;

    void start();
    void end();
}


/**
 * A single capture.  Contains a list of alternatives, in order of confidence.
 */
class SpeechResult {
    final List<String> alternatives;
    final bool isFinal;
    String get best => alternatives.isEmpty ? null : alternatives[0];
    bool get hasMultiple => alternatives.length > 1;

    SpeechResult(this.alternatives, this.isFinal);
}


class SpeechError {
    final bool networkProblem;
    final bool blocked;
    final bool userDenied;
    final bool aborted;
    bool get other => (! networkProblem && ! blocked &&
            ! userDenied && ! aborted);
    final String error;

    factory SpeechError(String err, DateTime startTime) {
        bool net = false;
        bool block = false;
        bool denied = false;
        bool abort = false;
        if (err == 'network') {
            net = true;
        } else if (err == 'not-allowed' || err == 'service-not-allowed') {
            // did the user block the access?
            if (startTime == null || startTime.difference(new DateTime.now()).
                    inMilliseconds > -200) {
                block = true;
            } else {
                denied = true;
            }
        } else if (err == 'aborted' || err == 'no-speech') {
            abort = true;
        }
        return new SpeechError._(err, net, block, denied, abort);
    }

    SpeechError._(this.error, this.networkProblem, this.blocked,
            this.userDenied, this.aborted);
}


List<String> NATIVE_SPEECH_API_NAME = [
    'SpeechRecognition',
    'webkitSpeechRecognition',
    'mozSpeechRecognition',
    'msSpeechRecognition',
    'oSpeechRecognition'
];


/**
 * Low-level speech API.
 */
class JsLowSpeechService extends LowSpeechService {
    final JsObject _js;
    bool _running = false;
    DateTime _startTime;
    final StreamController _startEvents = new StreamController.broadcast();
    final StreamController<SpeechResult> _resultEvents =
            new StreamController<SpeechResult>.broadcast();

    @override
    bool get isCapturing => _running;

    @override
    Stream get startEvents => _startEvents.stream;

    @override
    Stream<SpeechResult> get resultEvents => _resultEvents.stream;


    @override
    String get lang => _js['lang'];


    @override
    set lang(String lg) {
        _js['lang'] = lg;
    }

    final List<String> grammars = [];

    @override
    List<String> get supportedLanguages => grammars;


    JsLowSpeechService(this._js) {
        JsObject speechGrammars = _js['grammars'];
        int grammarLength = speechGrammars['length'];
        for (int i = 0; i < grammarLength; i++) {
            grammars.add(speechGrammars.callMethod('item', [ i ]));
        }

        _js['maxAlternatives'] = 5;

        _js['interimResults'] = true;

        // FIXME this should only be set to true if the site is on http.
        _js['continuous'] = true;

        _js['onStart'] = () {
print("+++ Speech: onStart");
            _startEvents.add("");
        };
        _js['onEnd'] = () {
print("+++ Speech: onEnd");
            if (_running) {
                // restart at most once a second
                Duration sinceStart = _startTime.difference(new DateTime.now());
                if (sinceStart.inMilliseconds < -1000) {
                    start();
                } else {
                    new Timer(new Duration(milliseconds: 1000) - sinceStart,
                        () {
                            start();
                        });
                }
            }
        };
        _js['onError'] = (JsObject event) {
print("+++ Speech: onError: ${event}");
            // FIXME if the error is severe enough, it means we just stop
            // trying to connect.

            _resultEvents.addError(new SpeechError(event['error'] as String,
                    _startTime));
        };
        _js['onResult'] = (JsObject event) {
print("+++ Speech: onResult: ${event}");
            List<String> alternatives = [];
            JsObject res = event['results'][event['resultIndex']];
            for (int i = 0; i < res['length']; i++) {
                String text = res.callMethod('item', [ i ]);
print("      [${text}]");
                alternatives.add(text.trim());
            }
            _resultEvents.add(new SpeechResult(alternatives, res['isFinal']));
        };
    }


    @override
    void start() {
print("+++ Speech: starting capture");
        _running = true;
        _startTime = new DateTime.now();
        _js.callMethod('start', []);
    }


    @override
    void end() {
print("+++ Speech: ending capture");
        _running = false;
        _js.callMethod('abort', []);
    }

}




/*
class JsSpeechRecognition extends AbstractSpeechRecognitionApi {
    final JsObject _js;

    factory JsSpeechRecognition(String objName) {
        JsObject js = new JsObject(context[objName]);
        if (js == null) {
            throw new Exception("unsupported speech recognition (${objName})");
        }

        return new JsSpeechRecognition._(js);
    }


    JsSpeechRecognition._(this._js) {

    }


}
*/