library piece_edit;

import 'dart:async';

/**
 * Load the value from the server.
 */
typedef Future<T> LoadValue<T>();

/**
 * Checks whether the given value is a valid value for the UI piece.
 * If it is valid, then the future should return `null`, otherwise
 * it returns a validation error message.
 */
typedef Future<String> ValidateValue<T>(T);

typedef Future SaveValue<T>(T);

/**
 * Manages the state for editing an individual piece of the UI.
 *
 * This assumes that the server-side value CANNOT be null.
 */
class PieceEdit<T> {
    final LoadValue<T> _loader;
    final SaveValue<T> _saver;
    final ValidateValue<T> _validator;

    bool _isEditing = false;
    bool get isEditing => _isEditing;

    bool _serverValueLoaded = false;
    bool get isServerValueLoaded => _serverValueLoaded;

    T _serverValue;
    T get original => _serverValue;

    T _editValue;
    T _validatedValue;
    set editValue(T value) {
        _editValue = value;
        if (value != _validatedValue || value != _serverValue) {
            // Force a validation call.
            _validator(value).then((String validationMessage) {
                _validationError = validationMessage;
                if (validationMessage == null) {
                    _validatedValue = value;
                }
            });
        }
    }
    T get editValue => _editValue;

    String _saveError = null;
    String _loadError = null;
    bool get isServerError => _saveError != null && _loadError != null;
    String get serverError => _saveError == null ? _loadError : _saveError;

    String _validationError = null;
    bool get isValidationError => _validationError != null;
    String get validationError => _validationError;

    bool get isAnyError => isServerError || isValidationError;

    bool get isValidChange => ! isValidationError && _editValue != original;

    /**
     * @param saver performs validation and save on the edited value.
     */
    PieceEdit(this._loader, this._validator, this._saver, [ T serverValue ]) {
        if (serverValue != null) {
            _serverValueLoaded = true;
            _serverValue = serverValue;
        } else {
            reload();
        }
    }

    /**
     * Starts editing the value.
     */
    void edit() {
        if (! _isEditing) {
            _isEditing = true;
            _editValue = original;
            _validatedValue = original;
            _validationError = null;
            _saveError = null;
        }
    }


    /**
     * Reloads the value from the source.
     */
    Future<T> reload() {
        return _loader().then((T t) {
            _loadError = null;
            _serverValue = t;
            _serverValueLoaded = true;
        }).catchError((e) {
            if (e is String) {
                _loadError = e;
            } else if (e != null) {
                _loadError = e.toString();
            }
        });
    }


    /**
     * Cancels editing mode.
     */
    void cancel() {
        _isEditing = false;
        _saveError = null;
        _validationError = null;
        _editValue = null;
        _validatedValue = null;
    }


    Future save() {
        if (_isEditing && ! isValidationError) {
            return _saver(_validatedValue).then((String msg) {
                _saveError = msg;
                if (msg == null) {
                    _isEditing = false;
                    _editValue = null;
                    _validatedValue = null;
                    _validationError = null;
                    return reload();
                }
            });
        } else {
            Completer completer = new Completer();
            completer.completeError(new Exception("not editing"));
            return completer.future;
        }
    }
}
