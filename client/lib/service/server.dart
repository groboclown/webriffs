
library server_service;

import 'dart:async';
import 'dart:convert';

import 'package:angular/angular.dart';
import 'package:logging/logging.dart';

import 'serverstatus.dart';


/**
 * Parent Service class that handles the communication with the
 * server via the RESTful Json API.
 */
class AbstractServerService {
    final ServerStatusService server;


    bool get isLoading => server.isLoading;


    AbstractServerService(this.server);
}
