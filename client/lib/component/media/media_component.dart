library media_component;

import 'dart:async';
import 'dart:html';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import '../../json/branch_details.dart';

import 'media_status.dart';
import 'media_status_loader.dart';



/**
 * The generic top-level media component that loads the dynamically loaded
 * media component, based on the media type.
 *
 * When the page is changes, this will force the underlying media component
 * to stop.
 */
@Component(
    selector: 'media-controller',
    template: '<script>var media_config = null;</script><div id="media"></div>',
    publishAs: 'cmp')
class MediaComponent extends ShadowRootAware implements DetachAware {
    final Logger _log = new Logger('components.MediaComponent');

    final Compiler _compiler;
    final Injector _injector;
    final Scope _scope;
    final DirectiveMap _directives;
    RouteHandle _route;

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
                _log.warning("Media component setting up the branch details.");
                _realDetails = bd;
                _realMediaFuture = loadMediaStatusService(bd);
                _realMediaFuture.then((MediaStatusService mediaService) {
                    setRealMedia(mediaService);
                });
                _mediaConnector.future
                    .then((MediaStatusServiceConnector connector) {
                        _log.warning("MediaComponent connecting to connector");
                        connector.connect(_realMediaFuture);
                    });
                _publicMedia.complete(_realMediaFuture);
                _publicDetails.complete(bd);
            }).catchError((Object error, StackTrace stack) {
                _log.severe("Error loading details", error, stack);
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
            this._directives, RouteProvider routeProvider) {
        _route = routeProvider.route.newHandle();
        _route.onPreLeave.listen((RouteEvent e) {
            media.then((MediaStatusService mss) {
                mss.pageUnloaded();
            });
        });
        _route.onEnter.listen((RouteEvent e) {
            _onEnter();
        });

        // At this point, the component is loaded and is rendering.
        // It can begin the page loaded logic as soon as the service
        // is loaded.
        _onEnter();
    }

    void detach() {
        // The route handle must be discarded.
        _route.discard();
    }

    void _onEnter() {
        _log.info("MediaComponent page entered");
        media.then((MediaStatusService mss) {
            _log.info("MediaComponent: send page loaded due to onEnter command");
            mss.pageLoaded();
        });
    }

    void onShadowRoot(ShadowRoot shadowRoot) {
        media.then((MediaStatusService mediaService) {
            _log.warning("Setting up the media element to " + mediaService.htmlTag);
            setRealMedia(mediaService);
            DivElement inner = shadowRoot.querySelector('#media');
            inner.appendHtml('<' + mediaService.htmlTag +
                    ' media="cmp.media"></' + mediaService.htmlTag + '>');
            ViewFactory template = _compiler([ inner ], _directives);
            Scope childScope = _scope.createChild(_scope.context);

            // We need to create a DirectiveInjector
            // from our injector, but this doesn't seem to be the right
            // way.
            DirectiveInjector directiveInjector = new
                    DirectiveInjector(null, _injector, null, null,
                        null, childScope, null);

            template(childScope, directiveInjector, [ inner ]);
        });
    }


    void setRealMedia(MediaStatusService mediaService) {
        if (_realMedia == null) {
            _log.warning("Media service set to " + mediaService.htmlTag);
            // Just in case the future that sets this value comes
            // after this invocation.
            _realMedia = mediaService;
        }
    }
}

