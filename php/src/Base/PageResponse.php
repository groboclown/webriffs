<?php

namespace Base;

require_once (__DIR__ . '/PageRequest.php');


class PageResponse {


    /**
     * Creates the response for paged data.
     *
     * @param PageRequest $page
     * @param int $start
     * @param int $perPage
     * @param int $totalCount
     * @param array $data
     *            result data from the query
     * @return array json response data
     */
    public static function createPageResponse(PageRequest $pageReq,
                    int $totalCount, array $data) {
        if ($pageReq->perPage == 0) {
            throw new \Exception("per-page value was zero");
        }
        $page = (int) floor($start / $pageReq->perPage);
        $pageCount = (int) ceil($totalCount / $pageReq->perPage);
        $ret = array(
            "_metadata" => array(
                "page" => $page,
                "per_page" => $perPage,
                "page_count" => $pageCount,
                "record_count" => $totalCount,
                "sorted_by" => $pageReq->sortedBy,
                "sort_order" => $pageReq->sortOrder,
                'filters' => $pageReq->filters
            ),
            "result" => $data
        );
        return $ret;
    }
}
