library speech_recognition;

import 'dart:async';
//import 'dart:js';
import 'dart:html';


SpeechRecognitionApi createSpeechRecognition() {
    // Check for native Dart support
    if (SpeechRecognition.supported) {
        return new DartSpeechRecognition();
    }

    /*
    // Check for experimental Safari and Chrome support
    if (context['webkitSpeechRecognition']) {
        return new JsSpeechRecognition('webkitSpeechRecognition');
    }
    if (context['speechRecognition']) {
        return new JsSpeechRecognition(context['speechRecognition']);
    }
    if (context['SpeechRecognition']) {
        return new JsSpeechRecognition(context['SpeechRecognition']);
    }
    */

    // TODO check for the PocketSphinx support

    // Browser doesn't support a speech recognition API.
    return null;
}

class SpeechRecognitionTranscript {
    final String transcript;
    final double confidence;

    SpeechRecognitionTranscript(this.transcript, this.confidence);
}

class SpeechRecognitionTranscriptList {
    final List<SpeechRecognitionTranscript> alternatives;

    SpeechRecognitionTranscriptList(this.alternatives);

    SpeechRecognitionTranscript get best => alternatives.first;
}



class SpeechRecognitionResults {
    final Iterable<SpeechRecognitionTranscriptList> transcripts;

    SpeechRecognitionResults(this.transcripts);
}

abstract class SpeechRecognitionApi {
    bool get isCapturing;

    String get nativeDialect;
    set nativeDialect(String dialect);

    /**
     * Begins capturing the audio.  If the object is already capturing, then
     * this will raise an exception.
     */
    Future<SpeechRecognitionResults> capture({ String dialect: null });

    /**
     * Force stopping the capture stream.  If no capture is active, then
     * this will do nothing.
     */
    void stop();

    /**
     * Notices for when any capturing actually begins.  Useful for triggering
     * UI events.
     */
    Stream get onStart;

    /**
     * Notices for when the capturing actually stops.  Useful for triggering
     * UI events.
     */
    Stream get onStop;


    Iterable<String> get supportedDialects;
}



abstract class AbstractSpeechRecognitionApi implements SpeechRecognitionApi {
    Completer<SpeechRecognitionResults> _active;
    List<SpeechRecognitionTranscriptList> _transcripts;
    String _nativeDialect;
    StreamController _startEvents = new StreamController();
    StreamController _stopEvents = new StreamController();

    @override
    String get nativeDialect => _nativeDialect;

    @override
    set nativeDialect(String dialect) => _nativeDialect = dialect;

    @override
    bool get isCapturing => _active != null;

    Future<SpeechRecognitionResults> _beginCapture() {
        if (isCapturing) {
            throw new Exception("already capturing");
        }
        _active = new Completer<SpeechRecognitionResults>();
        _transcripts = [];
        return _active.future;
    }


    @override
    Stream get onStart => _startEvents.stream;

    @override
    Stream get onStop => _stopEvents.stream;


    Sink get _startSink => _startEvents.sink;

    Sink get _stopSink => _stopEvents.sink;


    void _onCaptureTranscript(SpeechRecognitionTranscriptList transcript) {
        _transcripts.add(transcript);
    }


    void _onCaptureComplete() {
        _active.complete(new SpeechRecognitionResults(_transcripts));
        _active = null;
        _transcripts = null;
    }


    void _onCaptureError(Object error) {
        _active.completeError(error);
    }
}


class DartSpeechRecognition extends AbstractSpeechRecognitionApi {
    final SpeechRecognition _recog;
    Completer<SpeechRecognitionResults> _active;

    DartSpeechRecognition() :
            _recog = new SpeechRecognition() {
        nativeDialect = _recog.lang;
        _recog.onEnd.forEach((Event e) {
            if (isCapturing) {
                _onCaptureComplete();
            }
            _stopSink.add(null);
        });
        _recog.onError.forEach((Event e) {
            if (isCapturing) {
                Object val = null;
                if (e is ErrorEvent) {
                    val = e.error;
                }
                _onCaptureError(val);
            }
            _stopSink.add(null);
        });
        _recog.onResult.forEach((SpeechRecognitionEvent e) {
            for (SpeechRecognitionResult r in e.results) {
                if (r.isFinal) {
                    // Just grab the first result; don't take any other
                    // alternatives.
                    var transcripts = <SpeechRecognitionTranscript>[];
                    for (int i = 0; i < r.length; ++i) {
                        var transcript = new SpeechRecognitionTranscript(
                                r.item(i).transcript,
                                r.item(i).confidence);
                    }
                    _onCaptureTranscript(
                            new SpeechRecognitionTranscriptList(transcripts));
                }
            }
        });
        _recog.onNoMatch.forEach((SpeechRecognitionEvent e) {
            _onCaptureTranscript(new SpeechRecognitionTranscriptList([]));
        });

        _recog.onStart.forEach((_) => _startSink.add(null));
    }


    Future<SpeechRecognitionResults> capture({ String dialect: null }) {
        Future<SpeechRecognitionResults> ret = _beginCapture();
        _recog.continuous = true;
        _recog.interimResults = false;
        _recog.lang = (dialect == null)
            ? nativeDialect
            : dialect;
        _recog.start();
        return ret;
    }

    void stop() {
        if (isCapturing) {
            _recog.stop();
        }
    }

    Iterable<String> get supportedDialects =>
            _recog.grammars.map((SpeechGrammar g) => g.src);
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