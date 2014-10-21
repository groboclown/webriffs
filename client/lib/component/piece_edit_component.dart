library piece_edit_component;

import 'package:angular/angular.dart';
import 'piece_edit.dart';

@Component(
    selector: 'piece-editor',
    templateUrl: 'piece_edit_component.html')
class PieceEditComponent {
    @NgOneWayOneTime('piece')
    PieceEdit piece;

    @NgOneWayOneTime('can-edit')
    bool canEdit;

    // FIXME add bits about whether the piece is being validated / saved / etc
    // (the future is still active), and have that tie with the usability of
    // the UI elements.
}
