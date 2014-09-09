library media_status_loader;

import 'dart:async';

import '../../json/branch_details.dart';
import '../../json/film_details.dart';

import 'media_status.dart';
import 'stopwatch_media_component.dart';
import 'youtube_media_component.dart';


/**
 * All registered [MediaStatusService] factories.
 *
 * TODO There should be a way to add these within the component itself without
 * having to put them all here.  Probably through the Bind and Scope.
 */
final List<MediaStatusServiceFactory> MEDIA_STATUS_FACTORIES = [
    YOUTUBE_SERVICE_FACTORY

    // The stopwatch is the default factory, so we don't add it here.
];


/**
 * Returns the media services that match the branch.
 */
Future<MediaStatusService> loadMediaStatusService(BranchDetails branch) {
    // Check the links on the branch's film.
    // Use those to determine which media type to display.

    for (LinkRecord link in branch.filmLinks)
    {
        if (link.isPlaybackMedia) {
            for (MediaStatusServiceFactory factory in MEDIA_STATUS_FACTORIES) {
                var service = factory(branch, link);
                if (service != null) {
                    return service;
                }
            }
        }
    }


    // Default: show a stopwatch.
    return new Future<MediaStatusService>.value(
            new StopwatchMediaStatusService(branch));
}


