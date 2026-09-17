<?php $__view->extend('layouts.public'); ?>
<?php $__view->start('content'); ?>
<div class="container section container-sm">
    <div class="text-center mb-4">
        <span class="eyebrow">FAQ</span>
        <h1>Frequently asked questions</h1>
    </div>

    <div class="stack" style="--stack-gap:12px">
        <?php
        $faqs = [
            ['Do my visitors need an app?', 'No. A digital card is a normal web page — it opens in any browser on any phone or computer.'],
            ['Can I change the design later?', 'Yes, at any time. Your content is stored separately from the design, so switching a design never deletes anything.'],
            ['Can I use my own link?', 'Yes. You choose your own address, for example domain.com/ak-computer. You can change it later if the new one is available.'],
            ['What happens when my plan expires?', 'Depending on the platform settings your card either shows a renewal notice or goes offline. Your data is kept — renewing brings the card straight back.'],
            ['Is the QR code permanent?', 'Yes. The QR points at your card link, so printed QR codes keep working even after you change design or content.'],
            ['How do enquiries reach me?', 'Every enquiry is stored in your dashboard and, when email is configured, also sent to your inbox.'],
            ['Is my data private?', 'Only what you publish on the card is public. Your account details, leads and analytics are private to you.'],
            ['Can I create more than one card?', 'Yes — the number of cards depends on your plan. Each card has its own link, design and content.'],
            ['Do you support GST invoices?', 'Yes. When GST is enabled by the platform administrator, every paid order produces a GST invoice you can download.'],
        ];
        foreach ($faqs as [$question, $answer]): ?>
            <details class="card">
                <summary class="card-body" style="cursor:pointer;font-weight:700;list-style:none;display:flex;justify-content:space-between;gap:12px">
                    <span><?= e($question) ?></span>
                    <span class="faint"><?= icon('chevron-down', 18) ?></span>
                </summary>
                <div class="card-body" style="padding-top:0;color:var(--muted)"><?= e($answer) ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
<?php $__view->stop(); ?>
