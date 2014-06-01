
library paging;

import 'dart:async';

import '../service/server.dart';


typedef void PageLoadedFunc(PageState pageState, Iterable<dynamic> data);


/**
 * Keeps the state of a pagable list as returned by the server.  A page
 * component should have its own current values for sorting and filters and
 * current page value to represent what the user selects, so that the
 * changing of the UI elements can be separate from the submission.
 */
class PageState {
    final ServerStatusService _server;
    final PageLoadedFunc _on_load;
    final String _path;
    int _currentPage;
    int _recordsPerPage;
    int _pageCount;
    int _recordCount;
    String _sortOrder;
    String _sortedBy;
    bool _loadedFromServer;

    Map<String, dynamic> _filters;
    // FIXME list of possible sorted_by values

    PageState(this._server, this._path, this._on_load) :
            this._currentPage = 0,
            this._pageCount = 0,
            this._recordCount = 0,
            this._recordsPerPage = 0,
            this._loadedFromServer = false,
            this._sortOrder = 'A',
            this._filters = {} {

        for (String f in filter_names) {
            _filters[f] = null;
        }
    }


    Iterable<String> get filter_names => _filters.keys;

    int get currentPage => _currentPage;

    int get recordsPerPage => _recordsPerPage;

    int get pageCount => _pageCount;

    int get recordCount => _recordCount;

    String get sortOrder => _sortOrder;

    String get sortedBy => _sortedBy;

    bool get loadedFromServer => _loadedFromServer;

    bool get isDescSort => _sortOrder == 'D';


    Future<ServerResponse> updateFromServer({ int nextPage: null,
            int newRecordsPerPage: null, String newSortedBy: null,
            String newSortOrder: null,
            Map<String, dynamic> newFilters: null}) {
        if (nextPage == null) {
            nextPage = currentPage;
        }
        if (nextPage < 0) {
            nextPage = 0;
        } else if (nextPage > pageCount){
            nextPage = pageCount;
        }

        if (newRecordsPerPage == null) {
            newRecordsPerPage = recordsPerPage;
        }
        if (newRecordsPerPage < 5) {
            newRecordsPerPage = 5;
        }

        if (newSortedBy == null) {
            newSortedBy = sortedBy;
        }

        if (newSortOrder == null) {
            newSortOrder = sortOrder;
        }
        if (newSortOrder != 'D') {
            newSortOrder = 'A';
        }

        String path = _path + '?page=${nextPage}&per_page=' +
                '${newRecordsPerPage}&sort_order=${newSortOrder}&sort_by=' +
                Uri.encodeQueryComponent(newSortedBy);

        // filters - only add a filter if the page state allows for that filter
        if (newFilters == null) {
            newFilters = _filters;
        }
        _filters.forEach((String key, dynamic value) {
            if (newFilters.containsKey(key)) {
                if (newFilters[key] != null) {
                    path += '&' + Uri.encodeQueryComponent(key) + '=' +
                            Uri.encodeQueryComponent(newFilters[key]);
                }
            } else if (value != null) {
                path += '&' + Uri.encodeQueryComponent(key) + '=' +
                        Uri.encodeQueryComponent(value);
            }
        });

        return _server.get(path, null).then((ServerResponse response) {
            _loadedFromServer = true;
            List<dynamic> data = _fromJson(response.jsonData);
            _on_load(this, data);
            return response;
        });
    }


    Iterable<dynamic> _fromJson(Map<String, dynamic> json) {
        if (json.containsKey('_metadata') && json['_metadata'] is Map) {
            Map<String, dynamic> md = json['_metadata'];
            if (md.containsKey('page') && md['page'] is int) {
                _currentPage = md['page'];
            }
            if (md.containsKey('per_page') && md['per_page'] is int) {
                _recordsPerPage = md['per_page'];
            }
            if (md.containsKey('page_count') && md['page_count'] is int) {
                _pageCount = md['page_count'];
            }
            if (md.containsKey('record_count') && md['record_count'] is int) {
                _recordCount = md['record_count'];
            }
            if (md.containsKey('sorted_by') && md['sorted_by'] is String) {
                _sortedBy = md['sorted_by'];
            }
            if (md.containsKey('sort_order') && md['sort_order'] is String) {
                _sortOrder = md['sort_order'];
            }
            if (md.containsKey('filters') && md['filters'] is Map) {
                _filters.clear();
                md['filters'].foreach((String key, dynamic value) {
                    _filters[key] = value;
                });
            }
        }
        if (json.containsKey('result') && json['result'] is Iterable) {
            return json['result'];
        } else {
            return null;
        }
    }
}

