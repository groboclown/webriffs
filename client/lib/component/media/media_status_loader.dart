library media_status_loader;

import 'dart:async';

import '../../json/branch_details.dart';

import 'media_status.dart';
import 'stopwatch_media_component.dart';

Future<MediaStatusService> loadMediaStatusService(BranchDetails branch) {
    // Check the links on the branch's film.
    // Use those to determine which media type to display.

    // Default: show a stopwatch.
    return new Future<MediaStatusService>.value(
            new StopwatchMediaStatusService(branch));
}
