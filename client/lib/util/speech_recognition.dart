library speech_recognition;

import 'dart:async';
import 'dart:js';
import 'dart:html';


/**
 * A high level controller for managing voice input into a single input.
 * The input is split into phrases that represent a bit of voice that the
 * user gave.  These phrases can then be edited as desired.
 *
 * This API is still being tested - most of this should be just something
 * that the browser handles.
 *
 * **Lifecycle:**
 *
 * 1. Create a new instance when the user selects that they want to use voice
 *  control.
 * 2. call [start()] when the user wants to capture voice.
 * 3. call [end()] when the user finishes the voice capture.
 * 4. call [close()] when the voice control is no longer needed.
 */
class VoiceCaptureController {
    final LowSpeechService _service;
    final List<SpeechPhrase> _phrases = [];

    SpeechError _error = null;
    SpeechError get error => _error;
    bool get hasFatalError => _error != null &&
            _error.fatal;
    String get errorText => _error == null ? null : _error.error;

    Iterable<SpeechPhrase> get phrases => _phrases;

    Completer<String> _capturing = null;
    bool _captureState = false;
    bool get isCapturing => _captureState;

    int _insertIndex = 0;

    String _interim = "";
    String get interimTranscript => _interim;

    String _final = "";
    String get finalTranscript => _final;

    VoiceCaptureController(this._service) {
        _service.resultEvents
        .handleError((SpeechError err) {
            _error = err;
        }).listen((SpeechResult res) {
            // Still have some potential capture in progress, even if the
            // user requested a stop.
            _updatePhrase(res);
        });
        _service.start();
    }

    /**
     * Start capturing the speech input.  When the [end()] method is called,
     * this will trigger a stop request, which will cause the returned future
     * to complete with the final text.
     */
    Future<String> start() {
        if (! _service.isCapturing) {
            throw new Exception("service is closed");
        }
        if (isCapturing) {
            throw new Exception("illegal state - already capturing");
        }
        _insertIndex = 0;
        _capturing = new Completer<String>();
        _captureState = true;
        return _capturing.future;
    }

    /**
     * User command to complete the current voice input.  More may happen
     * later.
     */
    void end() {
        if (! isCapturing) {
            // Just stop
            return;
        }
        _captureState = false;
        for (SpeechPhrase sp in _phrases) {
            if (! sp.isFinal) {
                // text still in progress; keep capturing
                return;
            }
        }
        _endCapture(false);
    }


    void cancel() {
        if (! isCapturing) {
            return;
        }
        _captureState = false;
        if (_capturing != null) {
            _endCapture(true);
            // don't clear the error
        }
    }


    void editPhrase(SpeechPhrase phrase) {
        throw new UnimplementedError();
    }

    void removePhrase(SpeechPhrase phrase) {
        throw new UnimplementedError();
    }

    void close() {
        cancel();
        _service.end();
    }

    void _updatePhrase(SpeechResult result) {
        if (_capturing == null) {
            return;
        }
        if (result == null) {
            // handle end of stream
            _endCapture(false);
            return;
        }

        // Clear out any previous errors, since we now have a valid phrase.
        _error = null;

        // Check if we're editing a phrase, inserting a phrase, updating
        // a phrase, or adding a phrase.

        bool active = true;
        for (SpeechPhrase sp in _phrases) {
            if (active) {
                if (sp.source.index == result.index) {
                    // update
                    sp.reviseWith(result);
                    active = false;
                } else if (sp.isEditing) {
                    // edit
                    sp.reviseWith(result);
                    active = false;
                    // Finish the edit
                    _insertIndex = _phrases.length;
                }
            }
        }

        if (active && _insertIndex >= 0) {
            _phrases.insert(_insertIndex++, new SpeechPhrase(result));
        }

        // Now update the text construction
        _interim = "";
        _final = "";
        bool foundInterim = false;
        for (SpeechPhrase sp in _phrases) {
            if (sp.isFinal) {
                _final += " " + sp.best;
                _interim += " " + sp.best;
            } else {
                _interim += " ?" + sp.best + "?";
                foundInterim = true;
            }
        }

        if (! foundInterim && ! _captureState) {
            // Officially end the capture.
            _endCapture(false);
        }
    }

    void _endCapture(bool isCancel) {
        if (isCancel) {
            _capturing.completeError(
                    new SpeechError("abort", new DateTime.now()));
        } else if (_error != null) {
            _capturing.completeError(_error);
        } else {
            _capturing.complete(finalTranscript);
        }
        _capturing = null;
        _phrases.clear();
        _interim = "";
        _final = "";
    }
}



/**
 * Represents a single phrase that the user spoke.  This can be then edited
 * or removed as necessary.
 */
class SpeechPhrase {
    static int _phraseCount = 0;
    int _index;

    /// The index order for the phrase; if the text is replaced with something
    /// else, then this remains the same index.
    int get index => _index;

    SpeechResult _source;
    SpeechResult get source => _source;
    String selection;
    Iterable<String> get choices => _source.alternatives;
    String get best => _source.best;
    bool get hasMultiple => _source.hasMultiple;
    bool get isFinal => _source.isFinal;
    bool isEditing = false;
    bool isDeleted = false;

    SpeechPhrase(SpeechResult s) {
        _index = _phraseCount++;
        reviseWith(s);
    }

    void reviseWith(SpeechResult s) {
        _source = s;
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
    final int index;
    String get best => alternatives.isEmpty ? null : alternatives[0];
    bool get hasMultiple => alternatives.length > 1;

    SpeechResult(this.alternatives, this.isFinal, this.index);
}


class SpeechError {
    final bool networkProblem;
    final bool blocked;
    final bool userDenied;
    final bool aborted;
    bool get other => (! networkProblem && ! blocked &&
            ! userDenied && ! aborted);
    bool get fatal => networkProblem || blocked || userDenied;
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

        _js['onstart'] = (Event e) {
            _startEvents.add("");
        };
        _js['onend'] = (Event e) {
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
            } else {
                // FIXME this isn't the most elegant approach.
                _resultEvents.add(null);
            }
        };
        _js['onerror'] = (ErrorEvent event) {
            // FIXME if the error is severe enough, it means we just stop
            // trying to connect.

            SpeechError error = new SpeechError(event.error,
                    _startTime);
            if (error.blocked || error.userDenied || error.networkProblem) {
                _running = false;
            }

            _resultEvents.addError(error);
        };
        _js['onresult'] = (SpeechRecognitionEvent event) {
            if (event.results == null) {
                // probably not a valid voice recognition api
                _resultEvents.addError(
                        new SpeechError('no-speech', _startTime));
                return;
            }
            List<SpeechRecognitionResult> results = event.results;
            for (var ri = event.resultIndex; ri < results.length; ri++) {
                SpeechRecognitionResult res = results[ri];
                List<String> alternatives = [];
                for (int i = 0; i < res.length; i++) {
                    String text = res.item(i).transcript;
                    alternatives.add(text.trim());
                }
                _resultEvents.add(
                        new SpeechResult(alternatives, res.isFinal, ri));
            }
        };
    }


    @override
    void start() {
        _running = true;
        _startTime = new DateTime.now();
        _js.callMethod('start', []);
    }


    @override
    void end() {
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