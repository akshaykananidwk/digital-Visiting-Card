<?php
/**
 * Landing page.
 *
 * @var App\Core\View $__view
 * @var array<int,array<string,mixed>> $featured
 * @var array<int,array<string,mixed>> $plans
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,int> $stats
 */
$__view->extend('layouts.public');
?>
<?php $__view->start('hero'); ?>
<section class="hero">
    <div class="container">
        <div class="grid" style="grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);align-items:center;gap:40px">
            <div>
                <span class="eyebrow"><?= icon('sparkles', 14) ?> <?= number_format((int) ($stats['templates'] ?? 0)) ?>+ ready designs</span>
                <h1>Your business card, now a link you can WhatsApp.</h1>
                <p class="lead">
                    Build a premium mobile-first digital visiting card in minutes. One link and one QR code for calls,
                    WhatsApp, directions, products, gallery and enquiries — no app required.
                </p>
                <div class="row mt-3">
                    <a class="btn btn-lg" href="<?= e(url('register')) ?>"><?= icon('rocket', 18) ?> Create your digital card</a>
                    <a class="btn btn-lg btn-secondary" href="<?= e(url('templates')) ?>">Browse designs</a>
                </div>
                <div class="row mt-4" style="gap:28px">
                    <div>
                        <div class="bold" style="font-size:1.4rem"><?= number_format((int) ($stats['templates'] ?? 0)) ?>+</div>
                        <div class="small muted">Designs</div>
                    </div>
                    <div>
                        <div class="bold" style="font-size:1.4rem"><?= count($categories) ?></div>
                        <div class="small muted">Industry groups</div>
                    </div>
                    <div>
                        <div class="bold" style="font-size:1.4rem">3–5 min</div>
                        <div class="small muted">To publish</div>
                    </div>
                </div>
            </div>
            <div>
                <?php $preview = $featured[0] ?? null; ?>
                <div class="phone-frame">
                    <?php if ($preview !== null): ?>
                        <iframe src="<?= e(url_path('templates/preview/' . $preview['code'])) ?>" title="Live card preview" loading="lazy"></iframe>
                    <?php else: ?>
                        <div class="phone-screen" style="display:grid;place-items:center;color:#94a3b8">Preview</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<section class="section" style="padding-top:20px">
    <div class="container">
        <div class="grid grid-4">
            <?php
            $highlights = [
                ['zap', 'Instant sharing', 'One link for WhatsApp, Instagram, SMS and QR — opens in any browser.'],
                ['user-plus', 'Save contact', 'Visitors add you to their phone book with one tap as a vCard.'],
                ['bar-chart', 'Real analytics', 'See views, calls, WhatsApp clicks and enquiries per card.'],
                ['shield', 'Your data, yours', 'Only what you publish is public. Everything else stays private.'],
            ];
            foreach ($highlights as [$iconName, $heading, $text]): ?>
                <div class="card card-hover"><div class="card-body">
                    <div class="feature-icon"><?= icon($iconName, 22) ?></div>
                    <h3 style="font-size:1rem"><?= e($heading) ?></h3>
                    <p class="small muted mb-0"><?= e($text) ?></p>
                </div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($featured !== []): ?>
<section class="section" style="background:var(--surface);border-block:1px solid var(--border)">
    <div class="container">
        <div class="row-between mb-3">
            <div>
                <h2>Designs for every business</h2>
                <p class="muted mb-0">Computer shops, CCTV, doctors, hotels, professionals, temples and more.</p>
            </div>
            <a class="btn btn-secondary" href="<?= e(url('templates')) ?>">See all designs <?= icon('arrow-right', 16) ?></a>
        </div>

        <div class="template-grid">
            <?php foreach (array_slice($featured, 0, 8) as $template): ?>
                <a class="template-card" href="<?= e(url('templates/' . $template['code'])) ?>">
                    <div class="template-thumb">
                        <div class="template-badges">
                            <?php if ((int) $template['is_premium'] === 1): ?><span class="badge badge-warning">Premium</span><?php endif; ?>
                        </div>
                        <iframe src="<?= e(url_path('templates/preview/' . $template['code'])) ?>" title="<?= e((string) $template['name']) ?>" loading="lazy" tabindex="-1" scrolling="no"></iframe>
                    </div>
                    <div class="template-meta">
                        <div class="name truncate"><?= e((string) $template['name']) ?></div>
                        <div class="tiny muted"><?= e((string) $template['industry']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="text-center mb-4">
            <h2>Live in five minutes</h2>
            <p class="muted">No designer, no developer, no app installation.</p>
        </div>
        <div class="grid grid-4">
            <?php
            $steps = [
                ['1', 'Pick your industry', 'Choose the category that matches your business.'],
                ['2', 'Choose a design', 'Preview each design on a real phone frame before you pick.'],
                ['3', 'Add your details', 'Contact, services, products, gallery and business hours.'],
                ['4', 'Publish &amp; share', 'Get your link and QR code, then share on WhatsApp.'],
            ];
            foreach ($steps as [$number, $heading, $text]): ?>
                <div class="card"><div class="card-body">
                    <div class="feature-icon" style="font-weight:800;font-size:1.1rem"><?= $number ?></div>
                    <h3 style="font-size:1rem"><?= $heading ?></h3>
                    <p class="small muted mb-0"><?= $text ?></p>
                </div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($plans !== []): ?>
<section class="section" style="background:var(--surface);border-block:1px solid var(--border)">
    <div class="container">
        <div class="text-center mb-4">
            <h2>Simple pricing</h2>
            <p class="muted">Start free. Upgrade when you are ready.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach (array_slice($plans, 0, 3) as $plan): ?>
                <?= $__view->include('site.partials.plan-card', ['plan' => $plan]) ?>
            <?php endforeach; ?>
        </div>
        <p class="text-center mt-3"><a href="<?= e(url('pricing')) ?>">Compare all plans <?= icon('arrow-right', 14) ?></a></p>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="card" style="background:linear-gradient(135deg,var(--brand),#7c3aed);border:0;color:#fff">
            <div class="card-body text-center" style="padding:44px 24px">
                <h2 style="color:#fff">Create your digital visiting card today</h2>
                <p style="color:rgba(255,255,255,.85);max-width:52ch;margin-inline:auto">
                    Join businesses who replaced printed cards with a link that never runs out.
                </p>
                <a class="btn btn-lg btn-dark mt-2" href="<?= e(url('register')) ?>">Get started free</a>
            </div>
        </div>
    </div>
</section>
<?php $__view->stop(); ?>
