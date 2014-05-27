<?php


namespace WebRiffs;

use Tonic;
use PDO;
use Base;

class Resource extends Base\Resource {
    /**
     * Validate that the given variable is a non-null number.
     */
    protected function validateId($id, $name) {
        if ($id == null || !is_int($id)) {
            // TODO include the id name in the error
            throw new Base\ValidationException(array(
                    $name => "not valid"
                ));
        }
        return $id;
    }


    protected function getDB() {
        if ($this->container['dataStore']) {
            return $this->container['dataStore'];
        }
        try {
            $conn = new PDO($this->container['db_config-dsn'],
                $this->container['db_config-username'],
                $this->container['db_config-password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->container['dataStore'] = $conn;
            return $conn;
        } catch (Exception $e) {
            //throw new Tonic\NotFoundException;
            throw $e;
        }
    }
    
    
    protected function getSourceId($sourceName) {
        // For now, assume only one source.
        if (! $this->container['sources'][$sourceName] ||
                ! is_int($this->container['sources'][$sourceName]['id'])) {
            error_log("No registered source '".$sourceName."'");
            throw new Tonic\UnauthorizedException();
        }
        return $this->validateId($this->container['sources'][$sourceName]['id'],
            "source");
    }
    
    
    /**
     * Ensures the request is authenticated, and stores the user authentication
     * data in the container['user'].
     */
    function authenticated() {
        // FIXME this line causes a warning if the cookie isn't in the
        // request.  Need to find the correct way to check if the key exists.
        if (! array_key_exists(Resource::COOKIE_NAME, $_COOKIE)) {
            throw new Tonic\UnauthorizedException;
        }
        $cookie = $_COOKIE[Resource::COOKIE_NAME];
        
        $db = $this->getDB();
        $data = AuthenticationLayer::getUserSession($db, $cookie,
            $this->request->userAgent, $this->request->remoteAddr,
            null,
            Resource::DEFAULT_SESSION_TIMEOUT);
        
        $this->container['user'] = $data;
    }


    function secure(string $role, int $minLevel) {
        $this->authenticated();
        $db =& $this->getDB();

        $auth =& $this->container['user'];
        if (! isUserAuthSecureForRole($auth, $role)) {
            throw new Tonic\UnauthorizedException;
        }
        return true;
    }


    function isUserAuthSecureForRole($userAuth, $role) {
        if (!$userAuth) {
            return false;
        }
        foreach (array_keys($userAuth['attributes']) as $key) {
            if (startsWith($key, 'role_') && $userAuth['attributes'][$key] == $role) {
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * Processes a get request for paged data.
     *
     * @param string $defaultSortBy
     * @param int $defaultSortOrder
     * @param int $defaultSize
     * @param int $minSize
     * @param int $maxSize
     * @return multitype:number Ambigous <string, unknown> Ambigous <number, unknown>
     */
    function getPageRequest(mixed $filters, string $defaultSortBy,
            int $defaultSortOrder = null, int $defaultSize = null,
            int $minSize = null, int $maxSize = null) {
        if ($defaultSortOrder === null) {
            $defaultSortOrder = 1;
        }
        if ($defaultSize === null) {
            $defaultSize = 25;
        }
        if ($minSize === null) {
            $minSize = 5;
        }
        if ($maxSize === null) {
            $maxSize = 100;
        }
        
        $page = 0;
        $perPage = $defaultSize;
        $sortBy = $defaultSortBy;
        $sortOrder = $defaultSortOrder;
        if (array_key_exists("page", $_GET) &&
                is_numeric($_GET["page"]) &&
                ($_GET["page"]*1 == (int)($_GET["page"]*1))) {
            $page = intval($_GET["page"]);
        }
        if (array_key_exists("per_page", $_GET) &&
                is_numeric($_GET["per_page"]) &&
                ($_GET["per_page"]*1 == (int)($_GET["per_page"]*1))) {
            $perPage = intval($_GET["per_page"]);
        }
        if ($perPage > $maxSize) {
            $perPage = $maxSize;
        }
        if ($perPage < $minSize) {
            $perPage = $minSize;
        }
        
        if (array_key_exists("sort_order", $_GET) &&
                is_numeric($_GET["sort_order"]) &&
                ($_GET["sort_order"]*1 == (int)($_GET["sort_order"]*1))) {
            $so = intval($_GET["sort_order"]);
            if ($so >= 0 && $so <= 2) {
                $sortOrder = $so;
            }
            // else it's ignored
        }
        
        if (array_key_exists("sort_by", $_GET)) {
            $sortBy = $_GET["sort_by"];
        }
        
        $startRow = $page * $perPage;

        $filterSet = array();
        foreach ($filters as $filter) {
            if (array_key_exists($filter, $_GET)) {
                $filterSet[$filter] = $_GET[$filter];
            } else {
                $filterSet[$filter] = null;
            }
        }
        
        $ret = array(
            "sort_by" => $sortBy,
            "sort_order" => $sortOrder,
            "start_row" => $startRow,
            "row_count" => $perPage,
            "filters" => $filterSet,
        );
        return $ret;
    }
    
    
    /**
     * Creates the response for paged data.
     *
     * @param int $start
     * @param int $perPage
     * @param int $totalCount
     * @param mixed $data
     */
    function createPageResponse(string $sortedBy, int $sortOrder, int $start,
            int $perPage, int $totalCount, mixed $data) {
        if ($perPage == 0) {
            throw new \Exception("per-page value was zero");
        }
        $page = (int) floor($start / $perPage);
        $pageCount = (int) ceil($totalCount / $perPage);
        $ret = array(
            "_metadata" => array(
                "page" => $page,
                "per_page" => $perPage,
                "page_count" => $pageCount,
                "record_count" => $totalCount,
                "sorted_by" => $sortedBy,
                "sort_order" => $sortOrder,
                // TODO add filter information?
            ),
            "result" => $data
        );
        return $ret;
    }

    
    
    
    const COOKIE_NAME = "WRAUTHCK";
    const DEFAULT_SESSION_TIMEOUT = 360;
}
