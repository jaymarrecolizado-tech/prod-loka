<?php
/**
 * Shared list-table UI helpers (search field markup).
 * Pagination: list_pagination.php — Sort: table_sort.php
 */

/**
 * Standard Search field for filter forms (always visible on data tables).
 */
function listSearchFieldHtml(
    string $value,
    string $placeholder = 'Search...',
    string $inputClass = 'input input-bordered input-sm w-full bg-base-100',
    string $name = 'q'
): string {
    $html = '<div class="flex flex-col gap-1.5 flex-1 min-w-[200px]">';
    $html .= '<label class="label py-0"><span class="label-text text-xs font-semibold text-base-content/70 uppercase tracking-wide">Search</span></label>';
    $html .= '<input type="text" name="' . e($name) . '" value="' . e($value) . '"';
    $html .= ' placeholder="' . e($placeholder) . '" class="' . e($inputClass) . '" autocomplete="off">';
    $html .= '</div>';
    return $html;
}
