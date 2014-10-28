/**
 * Voice input, similar to a text input field.
 */

library voice_input_component;

import 'package:angular/angular.dart';
import '../util/speech_recognition.dart';

@Component(
    selector: 'voice-input',
    templateUrl: 'packages/webriffs_client/component/voiceinput_component.html')
class VoiceInputComponent {
    @NgOneWayOneTime('controller')
    VoiceCaptureController controller;

// FIXME
}
