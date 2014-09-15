library piece_edit_component;

import 'package:angular/angular.dart';
import 'piece_edit.dart';

@Component(
    selector: 'piece-editor',
    templateUrl: 'packages/webriffs_client/component/piece_edit_component.html',
    publishAs: 'cmp')
class PieceEditComponent {
    @NgOneWay('piece')
    PieceEdit piece;

    @NgOneWay('can-edit')
    bool canEdit;

    // FIXME add bits about whether the piece is being validated / saved / etc
    // (the future is still active), and have that tie with the usability of
    // the UI elements.
}
