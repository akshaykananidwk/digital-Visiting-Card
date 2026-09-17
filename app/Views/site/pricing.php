<?php
/** @var array<int,array<string,mixed>> $plans @var bool $gst @var float $gstRate */
$__view->extend('layouts.public');
?>
<?php $__view->start('content'); ?>
<div class="container section">
    <div class="text-center mb-4">
        <span class="eyebrow">Pricing</span>
        <h1>Plans that grow with your business</h1>
        <p class="lead" style="margin-inline:auto">Start free. Upgrade when you need more cards, products or premium designs.</p>
        <?php if ($gst): ?><p class="small muted">Prices exclude <?= e((string) $gstRate) ?>% GST unless stated otherwise.</p><?php endif; ?>
    </div>

    <div class="grid grid-3">
        <?php foreach ($plans as $plan): ?>
            <?= $__view->include('site.partials.plan-card', ['plan' => $plan]) ?>
        <?php endforeach; ?>
    </div>

    <div class="card mt-4"><div class="card-body table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <?php foreach ($plans as $plan): ?><th class="text-center"><?= e((string) $plan['name']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    'Digital cards'      => 'card_limit',
                    'Products per card'  => 'product_limit',
                    'Services per card'  => 'service_limit',
                    'Gallery images'     => 'gallery_limit',
                    'Videos'             => 'video_limit',
                    'Storage (MB)'       => 'storage_limit_mb',
                ];
                foreach ($rows as $label => $key): ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <?php foreach ($plans as $plan): ?>
                            <td class="text-center"><?= (int) $plan[$key] < 0 ? '∞' : number_format((int) $plan[$key]) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>

                <?php
                $flags = [
                    'Premium &amp; 3D designs' => 'premium_templates',
                    'Remove branding'          => 'remove_branding',
                    'Analytics dashboard'      => 'analytics',
                    'QR download'              => 'qr_download',
                    'Save contact (vCard)'     => 'vcard',
                    'Enquiry form'             => 'enquiry_form',
                    'SEO controls'             => 'seo_controls',
                    'Custom domain ready'      => 'custom_domain',
                    'API access'               => 'api_access',
                    'Priority support'         => 'priority_support',
                ];
                foreach ($flags as $label => $key): ?>
                    <tr>
                        <td><?= $label ?></td>
                        <?php foreach ($plans as $plan): ?>
                            <td class="text-center">
                                <?php if ((int) ($plan[$key] ?? 0) === 1): ?>
                                    <span style="color:var(--success)"><?= icon('check', 16) ?></span>
                                <?php else: ?>
                                    <span class="faint"><?= icon('x', 16) ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php $__view->stop(); ?>
