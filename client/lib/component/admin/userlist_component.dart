
library userlist_component;

import 'dart:async';

import 'package:angular/angular.dart';

import '../../service/server.dart';
import '../../service/user.dart';

import '../../util/async_component.dart';

/**
 * The UI component view of the list of films.
 */
@Component(
    selector: 'user-list',
    templateUrl: 'packages/webriffs_client/component/admin/userlist_component.html'
    //cssUrl: 'userlist_component.css'
    )
class UserListComponent extends PagingComponent {
    final UserService _user;

    final ServerStatusService _server;

    final List<HeaderUserInfo> users = [];

    UserInfo _currentUser;

    bool get canViewUsers => _user.isAdmin;

    AsyncComponent get cmp => this;

    bool get noUsers => users.isEmpty;

    bool get hasUsers => users.isNotEmpty;

    UserListComponent(this._user, ServerStatusService server) :
            _server = server,
            super(server, '/user', csrfTokenId: 'read_users') {
        update(newRecordsPerPage: 25);
        _currentUser = _user.info;
        _user.createUserChangedEventStream().listen((UserInfo info) {
            _currentUser = info;
        });
    }


    Future<ServerResponse> onSuccess(Iterable<dynamic> data) {
        users.clear();
        data.forEach((Map<String, dynamic> json) {
            users.add(new HeaderUserInfo.fromJson(json));
        });
        return null;
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
