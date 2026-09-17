<?php
/**
 * Shared hero contents (avatar, name, designation, badges). Layout partials
 * compose this so that content stays identical across every design.
 *
 * @var App\Services\CardPresenter $card
 */
$profile = $card->image('profile_image');
$logo = $card->image('logo_image');
?>
<div class="dvc-avatar-wrap">
    <?php if ($profile !== null): ?>
        <img class="dvc-avatar" src="<?= e($profile) ?>" alt="<?= e($card->displayName()) ?>" width="128" height="128" fetchpriority="high">
    <?php else: ?>
        <div class="dvc-avatar" style="display:grid;place-items:center;font-size:2.4rem;font-weight:800;color:var(--c-primary);background:var(--c-primary-soft)">
            <?= e(mb_strtoupper(mb_substr($card->displayName(), 0, 1))) ?>
        </div>
    <?php endif; ?>
    <?php if ($logo !== null): ?>
        <img class="dvc-logo" src="<?= e($logo) ?>" alt="<?= e((string) $card->get('business_name', 'Logo')) ?>" width="42" height="42" loading="lazy">
    <?php endif; ?>
</div>

<div>
    <h1 class="dvc-name"><?= e($card->displayName()) ?></h1>
    <?php if ($card->has('designation')): ?>
        <p class="dvc-designation"><?= e((string) $card->get('designation')) ?></p>
    <?php endif; ?>
    <?php if ($card->has('business_name')): ?>
        <p class="dvc-business"><?= e((string) $card->get('business_name')) ?></p>
    <?php endif; ?>
    <?php if ($card->has('business_category')): ?>
        <span class="dvc-category"><?= e((string) $card->get('business_category')) ?></span>
    <?php endif; ?>
    <?php $open = $card->openState(); ?>
    <?php if ($open !== null): ?>
        <div style="margin-top:10px">
            <span class="dvc-open-badge <?= e($open['state']) ?>"><span style="width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block"></span><?= e($open['label']) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($card->has('tagline')): ?>
        <p class="dvc-tagline"><?= e((string) $card->get('tagline')) ?></p>
    <?php endif; ?>
</div>
