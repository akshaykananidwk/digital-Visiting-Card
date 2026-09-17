<?php
/**
 * Pagination control.
 *
 * @var array{total:int,page:int,per_page:int,pages:int,from:int,to:int} $paginator
 * @var string $baseUrl  URL without the page parameter
 */
if (($paginator['pages'] ?? 1) <= 1) {
    return;
}
$page = (int) $paginator['page'];
$pages = (int) $paginator['pages'];
$query = $query ?? [];

$link = static function (int $target) use ($baseUrl, $query): string {
    $params = array_merge($query, ['page' => $target]);

    return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($params);
};

$window = 2;
$start = max(1, $page - $window);
$end = min($pages, $page + $window);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left', 16) ?></a>
    <?php else: ?>
        <span class="disabled"><?= icon('chevron-left', 16) ?></span>
    <?php endif; ?>

    <?php if ($start > 1): ?>
        <a href="<?= e($link(1)) ?>">1</a>
        <?php if ($start > 2): ?><span class="disabled">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e($link($i)) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="disabled">…</span><?php endif; ?>
        <a href="<?= e($link($pages)) ?>"><?= $pages ?></a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <a href="<?= e($link($page + 1)) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right', 16) ?></a>
    <?php else: ?>
        <span class="disabled"><?= icon('chevron-right', 16) ?></span>
    <?php endif; ?>
</nav>
<p class="text-center small muted" style="margin-top:8px">
    Showing <?= (int) $paginator['from'] ?>–<?= (int) $paginator['to'] ?> of <?= (int) $paginator['total'] ?>
</p>
