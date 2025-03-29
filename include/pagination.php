<?php

function generatePagination($currentPage, $totalPages, $url)
{
    $pagination = '<div class="pagination">';

    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        $pagination .= '<a href="' . $url . '?page=' . $i . '" class="button ' . $activeClass . '">' . $i . '</a>';
    }

    $pagination .= '</div>';
    return $pagination;
}

?>