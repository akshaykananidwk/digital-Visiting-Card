<?php
/**
 * Flash messages + validation error summary.
 *
 * @var array<string,array<int,string>> $flashes
 * @var array<string,array<int,string>> $errors
 */
$icons = ['success' => 'check-circle', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
?>
<?php foreach (($flashes ?? []) as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-<?= e($type) ?>" role="alert">
            <?= icon($icons[$type] ?? 'info', 18) ?>
            <div><?= e($message) ?></div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?= icon('alert', 18) ?>
        <div>
            <strong>Please correct the following:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <?php foreach ($errors as $messages): ?>
                    <?php foreach ((array) $messages as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
