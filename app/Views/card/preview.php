<?php
/**
 * Template preview document — the live card renderer fed with demo content.
 *
 * @var App\Core\View $__view
 * @var App\Services\CardPresenter $card
 * @var App\Services\TemplateRenderer $design
 * @var array<string,mixed> $template
 */
$sections = $card->visibleSections();
?><!doctype html>
<html lang="en" data-base="<?= e(url('/')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Preview · <?= e((string) $template['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($design->fontUrl()) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/card.css')) ?>">
<style>:root{<?= $design->cssVariables() ?>}body{overflow-x:hidden}</style>
</head>
<body>
<div class="<?= e($design->bodyClasses()) ?>" data-slug="preview" data-track="0">
    <?= $__view->include('card.layouts.' . $design->layout(), ['card' => $card, 'design' => $design, '__view' => $__view]) ?>

    <div class="dvc-shell">
        <?php foreach ($sections as $section): ?>
            <?php if ($section === 'qr' || $section === 'enquiry') { continue; } ?>
            <?= $__view->includeIf('card.sections.' . $section, [
                'card'   => $card,
                'design' => $design,
                'title'  => $card->sectionTitle($section),
                'qrDataUri' => '',
                '__view' => $__view,
            ]) ?>
        <?php endforeach; ?>

        <footer class="dvc-footer">
            <p style="margin:0" class="tiny"><?= e((string) $template['name']) ?> · preview</p>
        </footer>
    </div>

    <?= $__view->include('card.partials.sticky', ['card' => $card]) ?>
</div>
</body>
</html>
