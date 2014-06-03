<?php

namespace Base;

require_once(__DIR__.'/SearchFilter.php');

class PageRequest {
    
    /**
     * Construct a PageRequest based on the _GET parameters, and the passed
     * in values.
     *
     * @param array $filters list of SearchFilter values.
     * @param string $defaultSortBy
     * @param string $defaultSortOrder either "ASC" or "DESC".  The actual
     *      parameter must be either "A" or "D" to map to those values.
     * @param array $sortColumnMap a map of parameter string names to
     *      actual column names.
     * @param int $defaultSize
     * @param int $minSize
     * @param int $maxSize
     * @return PageRequest
     */
    public static function parseGetRequest($filters,
            $defaultSortBy, $sortColumnMap,
            $defaultSortOrder = "", $defaultSize = 25,
            $minSize = 5, $maxSize = 100) {
        $page = 0;
        $perPage = $defaultSize;
        $sortBy = $defaultSortBy;
        $sortOrder = $defaultSortOrder;
        if (array_key_exists("page", $_GET) &&
                is_numeric($_GET["page"]) &&
                ($_GET["page"]*1 == (int)($_GET["page"]*1))) {
            $page = intval($_GET["page"]);
        }
        if ($page < 0) {
            $page = 0;
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
        
        if (array_key_exists("sort_order", $_GET)) {
            $so = $_GET["sort_order"];
            if ($so == 'A') {
                $sortOrder = "ASC";
            } elseif ($so == 'D') {
                $sortOrder = "DESC";
            }
            // else it's ignored
        }
        
        if (array_key_exists("sort_by", $_GET)) {
            $sb = $_GET["sort_by"];
            if (array_key_exists($sb, $sortColumnMap)) {
                $sortBy = $sb;
            }
        }
        
        $startRow = $page * $perPage;
        $endRow = $startRow + $perPage;
        
        $filterSet = array();
        foreach ($filters as $filter) {
            $v = null;
            if (array_key_exists($filter->name, $_GET)) {
                $v = $_GET[$filter->name];
            }
            $filterSet[$filter->name] = $filter->parseValue($v);
        }
        
        $order = null;
        if ($sortBy !== null) {
            $order = $sortColumnMap[$sortBy];
            if ($sortOrder != null) {
                $order .= ' ' . $sortOrder;
            }
        }
        
        return new PageRequest($order, $sortBy, $sortOrder, $startRow, $endRow,
                $perPage, $filterSet);
    }


    public $order; // string
    public $sortby; // string
    public $sortOrder; // char
    public $startRow; // int
    public $endRow; // int
    public $perPage; // int
    public $filters; // array(string -> value)
    
    
    protected function __construct($order, $sortBy,
            $sortOrder, $startRow,
            $endRow, $perPage, $filters) {
        //parent::__construct();
        
        $this->order = $order;
        $this->sortBy = $sortBy;
        $this->sortOrder = substr($sortOrder, 0, 1);
        $this->startRow = $startRow;
        $this->endRow = $endRow;
        $this->perPage = $perPage;
        $this->filters = $filters;
    }
}
