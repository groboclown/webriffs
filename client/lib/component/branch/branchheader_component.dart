
library branchheader_component;

import 'dart:async';
import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../util/async_component.dart';
import '../../json/branch_details.dart';

import '../piece_edit.dart';

/**
 * handles editing or viewing the branch header information: the branch name,
 * description, labels, and permissions.  It handles can-view and can-edit
 * access rights, so the wrapper doesn't need to worry about it.
 *
 * FIXME this should only allow editing if the user is looking at the most
 * recent branch.  Historical viewing shouldn't allow editing.
 */
@Component(
    selector: 'branch-header',
    templateUrl: 'packages/webriffs_client/component/branch/branchheader_component.html',
    cssUrl: 'branchheader_component.css')
class BranchHeaderComponent extends BasicSingleRequestComponent {
    final ServerStatusService _server;

    AsyncComponent get cmp => this;

    BranchDetails _branch;

    @NgOneWayOneTime('branch')
    set branch(Future<BranchDetails> bdf) {
        bdf.then((BranchDetails bd) {
            _branch = bd;
            _componentReload();
        });
    }

    @NgOneWayOneTime('urlChangeId')
    int urlChangeId;

    bool _savePending = false;
    bool get isSavePending => _savePending &&
            (_nameEdit.isValidChange ||
            _descriptionEdit.isValidChange ||
            _tagsEdit.isValidChange);

    // Name edit variables
    bool get canEditName => _branch == null
            ? false : _branch.userCanEditBranch;
    PieceEdit<String> _nameEdit;
    PieceEdit<String> get nameEdit => _nameEdit;
    bool get isEditingName => canEditName && _nameEdit.isEditing;

    bool get canEditDescription => _branch == null
            ? false : _branch.userCanEditBranch;
    PieceEdit<String> _descriptionEdit;
    PieceEdit<String> get descriptionEdit => _descriptionEdit;
    bool get isEditingDescription => canEditDescription &&
            _descriptionEdit.isEditing;

    // Tag edit variables
    bool get canEditTags => _branch == null
            ? false : _branch.userCanEditBranchTags;
    PieceEdit<List<BranchTagDetails>> _tagsEdit;
    PieceEdit<List<BranchTagDetails>> get tagsEdit => _tagsEdit;
    bool get isEditingTags => canEditTags && _tagsEdit.isEditing;

    // Permission edit - done in another page.
    bool get canEditPermissions => _branch == null
            ? false : _branch.userCanEditBranchPermissions;

    int get filmId => _branch == null ? null : _branch.filmId;
    int get branchId => _branch == null ? null : _branch.branchId;
    String get filmName => _branch == null ? null : _branch.filmName;
    int get filmReleaseYear => _branch == null ? null : _branch.filmReleaseYear;
    int get changeId => _branch == null ? null : _branch.changeId;
    String get updatedOn => _branch == null ? null : _branch.updatedOn;

    bool get loaded => _branch != null;



    BranchHeaderComponent(ServerStatusService server) :
            _server = server,
            super(server) {
        _nameEdit = new PieceEdit<String>(
            // Loader
            () {
                if (_branch == null) {
                    return new Future.error("Please wait for the server to respond");
                } else {
                    return new Future.value(_branch.name);
                }
            },

            // Validator
            (String val) {
                return validateName(val);
            },

            // Saver
            (String val) {
                _savePending = true;
                _branch.name = val;
                return new Future.value();
            }

            // No initial value
            );
        _descriptionEdit = new PieceEdit<String>(
            // Loader
            () {
                if (_branch == null) {
                    return new Future.error("Please wait for the server to respond");
                } else {
                    return new Future.value(_branch.description);
                }
            },

            // Validator
            (String val) {
                // FIXME include maximum length
                return new Future.value(null);
            },

            // Saver
            (String val) {
                _savePending = true;
                _branch.description = val;
                return new Future.value();
            }

            // No initial value
            );
        _tagsEdit = new PieceEdit<List<BranchTagDetails>>(
            // Loader
            () {
                if (_branch == null) {
                    return new Future.error("Please wait for the server to respond");
                } else {
                    return new Future.value(_branch.tags);
                }
            },

            // Validator
            (List<BranchTagDetails> val) {
                // each tag is validated separately?
                return new Future.value(null);
            },

            // Saver
            (List<BranchTagDetails> val) {
                _savePending = true;
                _branch.tags = val;
                return new Future.value();
            }

            // No initial value
            );
    }


    @override
    void reload() {
        if (_branch != null) {
            _branch.updateFromServer(_server).then((_) {
                _componentReload();
            });
        }
    }


    Future<String> validateName(String name) {
        // Completely set the error state flags, and possibly load data
        // from the server if things on this side look fine.

        if (name == null) {
            return new Future.value("empty name");
        }
        if (! (name is String)) {
            return new Future.value("invalid type");
        } else if (name.length >= 200) {
            return new Future.value("name too long");
        } else if (_branch != null){
            String filmId = _branch.filmId.toString();
            String path = "/film/${filmId}/branchexists?Name=" +
                    Uri.encodeQueryComponent(name);
            return getFor(path).then((ServerResponse response) {
                if (response.jsonData != null &&
                        response.jsonData.containsKey('exists') &&
                        response.jsonData['exists'] == false) {
                    return null;
                } else {
                    return "name already exists";
                }
            });
        } else {
            return new Future.value("Please wait for server response");
        }

    }


    void cancel() {
        nameEdit.cancel();
        descriptionEdit.cancel();
        tagsEdit.cancel();
    }


    /**
     * Perform the real save on the data.
     */
    Future save() {
        Future f = new Future.value();
        if (nameEdit.isValidChange && nameEdit.isEditing) {
            f.then((_) => nameEdit.save());
        }
        if (descriptionEdit.isValidChange && descriptionEdit.isEditing) {
            f.then((_) => descriptionEdit.save());
        }
        if (tagsEdit.isValidChange && tagsEdit.isEditing) {
            f.then((_) => tagsEdit.save());
        }

        if (_savePending) {
            f.then((_) => addRequestWithToken(
                (ServerStatusService server, String token) =>
                    server.post(
                        "/branch/${_branch.branchId}/version",
                        token, data: _branch.toSaveJson())
                , "edit_branch").then((_) {
                    // FIXME on a successful save, the page should be
                    // reloaded to point to the new head revision.

                    _savePending = false;
                }));
        }

        return f;
    }


    void _componentReload() {
        // These can all run in parallel.
        nameEdit.reload();
        descriptionEdit.reload();
        tagsEdit.reload();
    }
}
