library speech_synthesis;

import 'dart:html';
import 'dart:async';

Speaker createSpeaker() {
    if (window.speechSynthesis == null) {
        return null;
    }
    try {
        return new Speaker();
    } catch (e) {
        // Not supported
        return null;
    }
}


class Speaker {
    List<SpeechSynthesisVoice> get voices => window.speechSynthesis.getVoices();

    bool get isSpeaking => window.speechSynthesis.speaking;

    bool get isPaused => window.speechSynthesis.paused;

    bool get isPending => window.speechSynthesis.pending;

    StreamController _onStart = new StreamController();
    Stream get onStart => _onStart.stream;

    StreamController _onEnd = new StreamController();
    Stream get onEnd => _onEnd.stream;

    StreamController _onError = new StreamController();
    Stream get onError => _onError.stream;

    StreamController _onPause = new StreamController();
    Stream get onPause => _onPause.stream;

    StreamController _onResume = new StreamController();
    Stream get onResume => _onResume.stream;

    StreamController _onBoundary = new StreamController();
    Stream get onBoundary => _onBoundary.stream;

    StreamController _onMark = new StreamController();
    Stream get onMark => _onMark.stream;

    SpeechSynthesisVoice voice;
    String lang;
    num pitch;
    num rate;
    num volume;

    Speaker() {
        if (window.speechSynthesis == null) {
            throw new Exception("speech synthesis not supported");
        }

        // FIXME set the defaults
    }

    Future speak(String text, {
            String lang: null,
            SpeechSynthesisVoice voice: null,
            num pitch: null,
            num rate: null,
            num volume: null
            }) {
        SpeechSynthesisUtterance utter = new SpeechSynthesisUtterance(text);
        lang = lang == null ? this.lang : lang;
        if (lang != null) {
            utter.lang = lang;
        }

        voice = voice == null ? this.voice : voice;
        if (voice != null) {
            utter.voice = voice;
        }

        pitch = pitch == null ? this.pitch : pitch;
        if (pitch != null) {
            utter.pitch = pitch;
        }

        rate = rate == null ? this.rate : rate;
        if (rate != null) {
            utter.rate = rate;
        }

        volume = volume == null ? this.volume : volume;
        if (volume != null) {
            utter.volume = volume;
        }

        Completer end = new Completer();
        utter.onEnd.forEach((_) => end.complete());
        utter.onError.forEach((_) => end.completeError(_));

        window.speechSynthesis.speak(utter);

        return end.future;
    }
}
