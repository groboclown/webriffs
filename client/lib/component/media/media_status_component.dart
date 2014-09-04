library media_status_component;

import 'dart:async';
import 'dart:html';

import 'package:angular/angular.dart';

import '../../json/branch_details.dart';

import 'media_status.dart';
import 'media_status_loader.dart';



/**
 * The generic top-level media component that loads the dynamically loaded
 * media component, based on the media type.
 */
@Component(
    selector: 'media-controller',
    templateUrl: 'packages/webriffs_client/component/media/media_component.html',
    publishAs: 'cmp')
class MediaComponent extends ShadowRootAware {
    final Compiler _compiler;
    final Injector _injector;
    final Scope _scope;
    final DirectiveMap _directives;

    @NgOneWay('branch-details')
    set branchDetails(Future<BranchDetails> details) {
        if (_realDetailsFuture != null) {
            throw new Exception("Invalid state: branchDetails already set");
        }
        if (details != null) {
            _realDetailsFuture = details;
            details.then((BranchDetails bd) {
                if (_realDetails != null) {
                    throw new Exception(
                            "Invalid state: Already fetched BranchDetails");
                }
                _realDetails = bd;
                _realMediaFuture = loadMediaStatusService(bd);
                _realMediaFuture.then((MediaStatusService mediaService) {
                    setRealMedia(mediaService);
                });
                _mediaConnector.future
                    .then((MediaStatusServiceConnector connector) {
                        connector.connect(_realMediaFuture);
                    });
                _publicMedia.complete(_realMediaFuture);
                _publicDetails.complete(bd);
            }).catchError((Object error, StackTrace stack) {
                _publicDetails.completeError(error, stack);
            });
        }
    }

    Future<BranchDetails> get branchDetails {
        if (_realDetailsFuture != null) {
            return _realDetailsFuture;
        }
        return _publicDetails.future;
    }

    Future<MediaStatusService> get media {
        if (_realMediaFuture != null) {
            return _realMediaFuture;
        }
        return _publicMedia.future;
    }

    final Completer<BranchDetails> _publicDetails =
            new Completer<BranchDetails>();
    final Completer<MediaStatusService> _publicMedia =
            new Completer<MediaStatusService>();

    Future<BranchDetails> _realDetailsFuture;
    BranchDetails _realDetails;

    Future<MediaStatusService> _realMediaFuture;
    MediaStatusService _realMedia;

    final Completer<MediaStatusServiceConnector> _mediaConnector =
            new Completer<MediaStatusServiceConnector>();

    /**
     * The owning component can be aware of the underlying media service.
     * It does this by having a `MediaStatusServiceConnector` act as the
     * proxy communication object between the different layers.
     */
    @NgOneWay('connector')
    set mediaConnector(MediaStatusServiceConnector connector) {
        _mediaConnector.complete(connector);
    }





    MediaComponent(this._compiler, this._injector, this._scope,
            this._directives);

    void onShadowRoot(ShadowRoot shadowRoot) {
        media.then((MediaStatusService mediaService) {
            setRealMedia(mediaService);
            DivElement inner = shadowRoot.querySelector('#media');
            inner.appendHtml('<' + mediaService.htmlTag +
                    ' media="cmp."></' + mediaService.htmlTag + '>');
            ViewFactory template = _compiler([ inner ], _directives);
            Scope childScope = _scope.createChild(_scope.context);
            Injector childInjector = _injector.createChild(
                    [new Module()..bind(Scope, toValue: childScope)]);
            template(childInjector, [ inner ]);
        });
    }


    void setRealMedia(MediaStatusService mediaService) {
        if (_realMedia == null) {
            // Just in case the future that sets this value comes
            // after this invocation.
            _realMedia = mediaService;
        }
    }
}

