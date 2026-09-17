<?php $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Generate the design library. Designs are produced from curated design tokens across <?= (int) $categories ?> industry categories — you can regenerate or add more at any time from Admin → Templates.</p>

    <?php if ((int) $existing > 0): ?>
        <div class="alert alert-info"><?= icon('info', 18) ?><div><?= (int) $existing ?> designs already exist. Running the generator again only adds the missing ones.</div></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url_path('install/designs')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="per_category">Designs per category</label>
            <input class="input" type="number" id="per_category" name="per_category" value="24" min="4" max="60">
            <div class="hint">24 per category creates roughly <?= (int) $estimate ?> designs. On a slow shared host, start with 12 and generate more later.</div>
        </div>
        <button class="btn btn-lg btn-block" type="submit"><?= icon('sparkles', 18) ?> Generate design library</button>
    </form>

    <p class="small muted mt-3 text-center">
        <a href="<?= e(url('install/security')) ?>">Skip for now</a> — you can generate designs later from the admin panel.
    </p>
<?php $__view->stop(); ?>
