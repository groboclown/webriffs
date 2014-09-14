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
}
