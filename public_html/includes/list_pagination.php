<?php
/**
 * Shared list pagination: default 10, choices 10 / 25 / 50 / 100.
 */

if (!defined('PER_PAGE_OPTIONS')) {
    define('PER_PAGE_OPTIONS', [10, 25, 50, 100]);
}
if (!defined('DEFAULT_PER_PAGE')) {
    define('DEFAULT_PER_PAGE', 10);
}

/**
 * Resolve per-page from request (clamped to allowed options).
 */
function resolvePerPage(string $param = 'per_page', ?int $default = null): int
{
    $default = $default ?? DEFAULT_PER_PAGE;
    if (!in_array($default, PER_PAGE_OPTIONS, true)) {
        $default = DEFAULT_PER_PAGE;
    }
    $raw = getInt($param, $default);
    return in_array($raw, PER_PAGE_OPTIONS, true) ? $raw : $default;
}

function resolveListPage(string $param = 'p'): int
{
    return max(1, getInt($param, 1));
}

/**
 * @return array{page:int,perPage:int,total:int,totalPages:int,offset:int,from:int,to:int}
 */
function listPaginationState(int $total, ?int $page = null, ?int $perPage = null): array
{
    $perPage = $perPage ?? resolvePerPage();
    $page = $page ?? resolveListPage();
    $total = max(0, $total);
    $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $from = $total === 0 ? 0 : $offset + 1;
    $to = min($offset + $perPage, $total);

    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'from' => $from,
        'to' => $to,
    ];
}

/**
 * Per-page select for filter forms (auto-submits on change).
 */
function perPageFieldHtml(int $current, string $selectClass = 'select select-bordered select-sm bg-base-100'): string
{
    $html = '<div class="flex flex-col gap-1.5 min-w-[100px]">';
    $html .= '<label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Per page</span></label>';
    $html .= '<select name="per_page" class="' . e($selectClass) . '" onchange="this.form.submit()">';
    foreach (PER_PAGE_OPTIONS as $n) {
        $sel = ((int) $n === (int) $current) ? ' selected' : '';
        $html .= '<option value="' . (int) $n . '"' . $sel . '>' . (int) $n . '</option>';
    }
    $html .= '</select></div>';
    return $html;
}

function listPaginationUrl(array $params, int $pageNum, string $pageParam = 'p'): string
{
    $params[$pageParam] = $pageNum;
    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });
    return '?' . http_build_query($params);
}

/**
 * Compact footer: “Showing X–Y of Z” + page join + optional per-page.
 */
function listPaginationFooter(array $state, array $queryParams, string $pageParam = 'p'): string
{
    $total = (int) $state['total'];
    if ($total <= 0) {
        return '';
    }

    $page = (int) $state['page'];
    $totalPages = (int) $state['totalPages'];
    $from = (int) $state['from'];
    $to = (int) $state['to'];
    $perPage = (int) $state['perPage'];

    $queryParams['per_page'] = $perPage;

    $html = '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4 px-1">';
    $html .= '<p class="text-sm text-base-content/60">Showing ' . $from . '–' . $to . ' of ' . number_format($total) . '</p>';

    if ($totalPages > 1) {
        $html .= '<div class="join">';
        if ($page > 1) {
            $html .= '<a class="join-item btn btn-sm" href="' . e(listPaginationUrl($queryParams, 1, $pageParam)) . '" title="First">&laquo;</a>';
            $html .= '<a class="join-item btn btn-sm" href="' . e(listPaginationUrl($queryParams, $page - 1, $pageParam)) . '" title="Previous">&lsaquo;</a>';
        } else {
            $html .= '<button type="button" class="join-item btn btn-sm btn-disabled" disabled>&laquo;</button>';
            $html .= '<button type="button" class="join-item btn btn-sm btn-disabled" disabled>&lsaquo;</button>';
        }

        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $page) {
                $html .= '<button type="button" class="join-item btn btn-sm btn-primary" disabled>' . $i . '</button>';
            } else {
                $html .= '<a class="join-item btn btn-sm" href="' . e(listPaginationUrl($queryParams, $i, $pageParam)) . '">' . $i . '</a>';
            }
        }

        if ($page < $totalPages) {
            $html .= '<a class="join-item btn btn-sm" href="' . e(listPaginationUrl($queryParams, $page + 1, $pageParam)) . '" title="Next">&rsaquo;</a>';
            $html .= '<a class="join-item btn btn-sm" href="' . e(listPaginationUrl($queryParams, $totalPages, $pageParam)) . '" title="Last">&raquo;</a>';
        } else {
            $html .= '<button type="button" class="join-item btn btn-sm btn-disabled" disabled>&rsaquo;</button>';
            $html .= '<button type="button" class="join-item btn btn-sm btn-disabled" disabled>&raquo;</button>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}
