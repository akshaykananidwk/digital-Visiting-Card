<?php /** @var array<string,mixed> $lead @var array<string,mixed>|null $card */ $__view->extend('layouts.panel'); ?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e(url('leads')) ?>"><?= icon('arrow-left', 15) ?> All leads</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="grid grid-split" style="align-items:start">
    <div class="card">
        <div class="card-header">
            <h2><?= e((string) $lead['name']) ?></h2>
            <span class="badge"><?= e((string) $lead['status']) ?></span>
        </div>
        <div class="card-body">
            <div class="grid grid-2 mb-3">
                <?php if (!empty($lead['phone'])): ?>
                    <div>
                        <div class="label">Phone</div>
                        <a class="bold" href="tel:<?= e((string) $lead['phone']) ?>"><?= e((string) $lead['phone']) ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($lead['email'])): ?>
                    <div>
                        <div class="label">Email</div>
                        <a class="bold" href="mailto:<?= e((string) $lead['email']) ?>"><?= e((string) $lead['email']) ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($lead['subject'])): ?>
                <div class="label">Subject</div>
                <p><?= e((string) $lead['subject']) ?></p>
            <?php endif; ?>

            <div class="label">Message</div>
            <p style="white-space:pre-line"><?= e((string) ($lead['message'] ?? '—')) ?></p>

            <div class="row mt-3" style="gap:8px">
                <?php if (!empty($lead['phone'])): ?>
                    <a class="btn btn-sm" href="tel:<?= e((string) $lead['phone']) ?>"><?= icon('phone', 15) ?> Call</a>
                    <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
                       href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $lead['phone'])) ?>"><?= icon('whatsapp', 15) ?> WhatsApp</a>
                <?php endif; ?>
                <?php if (!empty($lead['email'])): ?>
                    <a class="btn btn-sm btn-secondary" href="mailto:<?= e((string) $lead['email']) ?>"><?= icon('mail', 15) ?> Email</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <form method="post" action="<?= e(url_path('leads/' . (int) $lead['id'] . '/status')) ?>" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 style="font-size:.98rem">Update</h3></div>
            <div class="card-body">
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (['new' => 'New', 'read' => 'Read', 'contacted' => 'Contacted', 'converted' => 'Converted', 'spam' => 'Spam', 'archived' => 'Archived'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) $lead['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="notes">Private notes</label>
                    <textarea class="textarea" id="notes" name="notes" rows="4" maxlength="2000"><?= e((string) ($lead['notes'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="card-footer text-right"><button class="btn btn-sm" type="submit">Save</button></div>
        </form>

        <div class="card"><div class="card-body small">
            <div class="row-between" style="padding:5px 0"><span class="muted">Received</span><strong><?= e(date('d M Y, H:i', strtotime((string) $lead['created_at']))) ?></strong></div>
            <div class="row-between" style="padding:5px 0"><span class="muted">Source</span><strong><?= e((string) $lead['source']) ?></strong></div>
            <?php if ($card !== null): ?>
                <div class="row-between" style="padding:5px 0"><span class="muted">Card</span><a href="<?= e(url('cards/' . (int) $card['id'] . '/editor')) ?>"><?= e((string) $card['title']) ?></a></div>
            <?php endif; ?>
        </div></div>

        <form method="post" action="<?= e(url_path('leads/' . (int) $lead['id'] . '/delete')) ?>" data-confirm="Delete this lead permanently?">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-block btn-sm" type="submit" style="color:var(--danger)"><?= icon('trash', 14) ?> Delete lead</button>
        </form>
    </div>
</div>
<?php $__view->stop(); ?>
