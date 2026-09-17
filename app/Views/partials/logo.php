<?php
/** @var array{name:string,logo:?string} $branding */
$href = $href ?? url('/');
?>
<a class="logo" href="<?= e($href) ?>">
    <?php if (!empty($branding['logo'])): ?>
        <img src="<?= e($branding['logo']) ?>" alt="<?= e($branding['name']) ?>" width="34" height="34">
    <?php else: ?>
        <span class="logo-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($branding['name'], 0, 1))) ?></span>
    <?php endif; ?>
    <span><?= e($branding['name']) ?></span>
</a>
