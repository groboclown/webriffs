
library userdetail_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';

import '../../util/async_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'user-detail',
    templateUrl: 'packages/webriffs_client/component/admin/userdetail_component.html'
    //cssUrl: 'userdetail_component.css'
    )
class UserDetailComponent extends BasicSingleRequestComponent {
    final UserService _user;

    final ServerStatusService _server;

    final String username;

    UserInfo _currentUser;

    bool get canViewUser => _user.isAdmin;

    AsyncComponent get cmp => this;



    factory UserDetailComponent(UserService user, ServerStatusService server,
            RouteProvider routeProvider) {
        String username = routeProvider.parameters['username'];
        return new UserDetailComponent._(user, server, username);
    }

    UserDetailComponent._(this._user, ServerStatusService server,
            this.username) :
            _server = server,
            super(server) {
        _currentUser = _user.info;
        _user.createUserChangedEventStream().listen((UserInfo info) {
            _currentUser = info;
        });
    }


    @override
    void reload() {

    }
}



class HeaderUserInfo {
    final String username;
    final String contact;
    final bool isSiteAdmin;
    final String banStart;
    final String banEnd;
    final bool isPermaBanned;
    final String createdOn;
    final String lastUpdatedOn;

    HeaderUserInfo(this.username, this.contact, this.isSiteAdmin,
            this.banStart, this.banEnd, this.isPermaBanned,
            this.createdOn, this.lastUpdatedOn);

    factory HeaderUserInfo.fromJson(Map<String, dynamic> json) {
        // Ignore User_Id, Ga_User_Id, Primary_Source_Id
        return new HeaderUserInfo(
            json['Username'],
            json['Contact'],
            json['Is_Site_Admin'],
            json['Ban_Start'],
            json['Ban_End'],
            json['Is_Perma_Banned'],
            json['Created_On'],
            json['Last_Updated_On']);
    }

    /*
     * "Is_Site_Admin":true,
     * "Ban_Start":"2014-10-21 17:25:33",
     * "Ban_End":"2014-10-21 17:25:33",
     * "Is_Perma_Banned":false,"Created_On":"2014-10-21 17:25:33","Last_Updated_On":null
     */
}
