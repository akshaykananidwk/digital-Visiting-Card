<?php
/**
 * Live card editor.
 *
 * @var array<string,mixed> $card
 * @var array<string,mixed>|null $template
 * @var array<int,array<string,mixed>> $sections
 * @var array<string,string> $social
 * @var array<string,int> $counts
 * @var string $tab @var string $publicUrl @var bool $canSeo
 * @var array<string,array<string,mixed>> $platforms
 */
$__view->extend('layouts.panel');
$cardId = (int) $card['id'];
$hours = is_array($card['business_hours'] ?? null) ? $card['business_hours'] : [];
$settings = is_array($card['settings'] ?? null) ? $card['settings'] : [];
$days = App\Services\CardPresenter::DAYS;
$sectionMap = [];
foreach ($sections as $row) {
    $sectionMap[(string) $row['section']] = $row;
}
$tabs = [
    'profile'  => ['Profile', 'user-plus'],
    'business' => ['Business', 'briefcase'],
    'contact'  => ['Contact', 'phone'],
    'social'   => ['Social', 'share'],
    'sections' => ['Sections', 'layers'],
    'seo'      => ['SEO', 'search'],
    'settings' => ['Settings', 'settings'],
];
?>
<?php $__view->start('topbar'); ?>
<a class="btn btn-sm btn-secondary" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= icon('eye', 15) ?> View</a>
<?php $__view->stop(); ?>

<?php $__view->start('content'); ?>
<div class="card mb-3">
    <div class="card-body row-between">
        <div style="min-width:0">
            <div class="row" style="gap:8px;align-items:center">
                <?php
                $badge = match ((string) $card['status']) {
                    'published' => 'badge-success', 'expired' => 'badge-warning',
                    'suspended' => 'badge-danger', default => '',
                };
                ?>
                <span class="badge <?= $badge ?>"><?= e((string) $card['status']) ?></span>
                <span class="small truncate"><?= e($publicUrl) ?></span>
                <button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($publicUrl) ?>" data-copy-message="Card link copied"><?= icon('copy', 14) ?></button>
            </div>
        </div>
        <div class="row" style="gap:8px">
            <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/design')) ?>"><?= icon('layers', 15) ?> Design</a>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('cards/' . $cardId . '/qr')) ?>"><?= icon('qr', 15) ?> QR</a>
            <?php if ((string) $card['status'] === 'published'): ?>
                <form method="post" action="<?= e(url('cards/' . $cardId . '/unpublish')) ?>" data-confirm="Take this card offline?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-secondary" type="submit">Unpublish</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('cards/' . $cardId . '/publish')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-success" type="submit"><?= icon('rocket', 15) ?> Publish</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="editor">
    <div>
        <div class="tabs">
            <?php foreach ($tabs as $key => [$label, $iconName]): ?>
                <a href="<?= e(url('cards/' . $cardId . '/editor?tab=' . $key)) ?>" class="<?= $tab === $key ? 'active' : '' ?>">
                    <?= icon($iconName, 15) ?> <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'profile'): ?>
            <div class="card mb-3"><div class="card-body">
                <h3 style="font-size:1rem">Images</h3>
                <div class="grid grid-3">
                    <?php foreach ([
                        'profile_image' => 'Profile photo',
                        'cover_image'   => 'Cover image',
                        'logo_image'    => 'Business logo',
                    ] as $field => $label): ?>
                        <div>
                            <div class="label"><?= e($label) ?></div>
                            <?php if (!empty($card[$field])): ?>
                                <img class="upload-preview mb-1" src="<?= e((string) upload_url((string) $card[$field])) ?>" alt="">
                                <form method="post" action="<?= e(url('cards/' . $cardId . '/media/remove')) ?>" data-confirm="Remove this image?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="field" value="<?= e($field) ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 13) ?> Remove</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('cards/' . $cardId . '/media')) ?>" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="field" value="<?= e($field) ?>">
                                <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp" required style="font-size:.78rem;padding:7px">
                                <button class="btn btn-sm btn-secondary btn-block mt-1" type="submit"><?= icon('upload', 13) ?> Upload</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div></div>

            <form method="post" action="<?= e(url('cards/' . $cardId . '/profile')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="field">
                        <label class="required" for="title">Card name</label>
                        <input class="input" type="text" id="title" name="title" value="<?= e(old('title', (string) $card['title'])) ?>" required>
                        <div class="hint">Only you see this — it helps you tell your cards apart.</div>
                    </div>
                    <div class="grid grid-2" style="gap:0 16px">
                        <div class="field">
                            <label class="required" for="full_name">Full name</label>
                            <input class="input" type="text" id="full_name" name="full_name" value="<?= e(old('full_name', (string) ($card['full_name'] ?? ''))) ?>" required>
                        </div>
                        <div class="field">
                            <label for="designation">Designation</label>
                            <input class="input" type="text" id="designation" name="designation" value="<?= e(old('designation', (string) ($card['designation'] ?? ''))) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="tagline">Tagline</label>
                        <input class="input" type="text" id="tagline" name="tagline" value="<?= e(old('tagline', (string) ($card['tagline'] ?? ''))) ?>" maxlength="255">
                    </div>
                    <div class="field">
                        <label for="about">About</label>
                        <textarea class="textarea" id="about" name="about" rows="6" maxlength="5000"><?= e(old('about', (string) ($card['about'] ?? ''))) ?></textarea>
                        <div class="hint">Line breaks are preserved on the card.</div>
                    </div>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save profile</button></div>
            </form>

        <?php elseif ($tab === 'business'): ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/business')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="grid grid-2" style="gap:0 16px">
                        <div class="field">
                            <label for="business_name">Business name</label>
                            <input class="input" type="text" id="business_name" name="business_name" value="<?= e(old('business_name', (string) ($card['business_name'] ?? ''))) ?>">
                        </div>
                        <div class="field">
                            <label for="business_category">Category</label>
                            <input class="input" type="text" id="business_category" name="business_category" value="<?= e(old('business_category', (string) ($card['business_category'] ?? ''))) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="address">Address</label>
                        <textarea class="textarea" id="address" name="address" rows="2" maxlength="500"><?= e(old('address', (string) ($card['address'] ?? ''))) ?></textarea>
                    </div>

                    <div class="grid grid-4" style="gap:0 12px">
                        <div class="field"><label for="city">City</label><input class="input" type="text" id="city" name="city" value="<?= e(old('city', (string) ($card['city'] ?? ''))) ?>"></div>
                        <div class="field"><label for="state">State</label><input class="input" type="text" id="state" name="state" value="<?= e(old('state', (string) ($card['state'] ?? ''))) ?>"></div>
                        <div class="field"><label for="pincode">PIN code</label><input class="input" type="text" id="pincode" name="pincode" value="<?= e(old('pincode', (string) ($card['pincode'] ?? ''))) ?>"></div>
                        <div class="field"><label for="country">Country</label><input class="input" type="text" id="country" name="country" value="<?= e(old('country', (string) ($card['country'] ?? 'India'))) ?>"></div>
                    </div>

                    <div class="field">
                        <label for="map_link">Google Maps link</label>
                        <input class="input" type="url" id="map_link" name="map_link" value="<?= e(old('map_link', (string) ($card['map_link'] ?? ''))) ?>" placeholder="https://maps.app.goo.gl/…">
                    </div>
                    <div class="grid grid-2" style="gap:0 16px">
                        <div class="field"><label for="latitude">Latitude</label><input class="input" type="text" id="latitude" name="latitude" value="<?= e(old('latitude', (string) ($card['latitude'] ?? ''))) ?>"></div>
                        <div class="field"><label for="longitude">Longitude</label><input class="input" type="text" id="longitude" name="longitude" value="<?= e(old('longitude', (string) ($card['longitude'] ?? ''))) ?>"></div>
                    </div>

                    <fieldset>
                        <legend>Business hours</legend>
                        <?php foreach ($days as $key => $label): ?>
                            <?php $day = $hours[$key] ?? ['open' => '', 'close' => '', 'closed' => false]; ?>
                            <div class="row" style="gap:10px;align-items:center;margin-bottom:8px;flex-wrap:nowrap">
                                <span class="small bold" style="width:46px;flex:none"><?= e(substr($label, 0, 3)) ?></span>
                                <input class="input" type="time" name="hours_<?= e($key) ?>_open" value="<?= e((string) ($day['open'] ?? '')) ?>" style="max-width:130px">
                                <span class="muted small">to</span>
                                <input class="input" type="time" name="hours_<?= e($key) ?>_close" value="<?= e((string) ($day['close'] ?? '')) ?>" style="max-width:130px">
                                <label class="checkbox" style="margin-left:auto;white-space:nowrap">
                                    <input type="checkbox" name="hours_<?= e($key) ?>_closed" value="1" <?= !empty($day['closed']) ? 'checked' : '' ?>>
                                    <span class="small">Closed</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset>
                        <legend>Payments</legend>
                        <div class="grid grid-2" style="gap:0 16px">
                            <div class="field">
                                <label for="upi_id">UPI ID</label>
                                <input class="input" type="text" id="upi_id" name="upi_id" value="<?= e(old('upi_id', (string) ($card['upi_id'] ?? ''))) ?>" placeholder="name@bank">
                            </div>
                            <div class="field">
                                <label for="payment_note">Payment note</label>
                                <input class="input" type="text" id="payment_note" name="payment_note" value="<?= e(old('payment_note', (string) ($card['payment_note'] ?? ''))) ?>" placeholder="UPI, card and cash accepted">
                            </div>
                        </div>
                    </fieldset>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save business details</button></div>
            </form>

        <?php elseif ($tab === 'contact'): ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/contact')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="grid grid-2" style="gap:0 16px">
                        <div class="field"><label for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone', (string) ($card['phone'] ?? ''))) ?>"></div>
                        <div class="field"><label for="phone_alt">Alternate phone</label><input class="input" type="tel" id="phone_alt" name="phone_alt" value="<?= e(old('phone_alt', (string) ($card['phone_alt'] ?? ''))) ?>"></div>
                    </div>
                    <div class="grid grid-2" style="gap:0 16px">
                        <div class="field"><label for="whatsapp">WhatsApp</label><input class="input" type="tel" id="whatsapp" name="whatsapp" value="<?= e(old('whatsapp', (string) ($card['whatsapp'] ?? ''))) ?>"></div>
                        <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email', (string) ($card['email'] ?? ''))) ?>"></div>
                    </div>
                    <div class="field">
                        <label for="website">Website</label>
                        <input class="input" type="url" id="website" name="website" value="<?= e(old('website', (string) ($card['website'] ?? ''))) ?>" placeholder="https://">
                    </div>
                    <div class="field">
                        <label for="whatsapp_message">Pre-filled WhatsApp message</label>
                        <input class="input" type="text" id="whatsapp_message" name="whatsapp_message" value="<?= e(old('whatsapp_message', (string) ($card['whatsapp_message'] ?? ''))) ?>" maxlength="255">
                        <div class="hint">This text is waiting in the chat box when someone taps WhatsApp.</div>
                    </div>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save contact details</button></div>
            </form>

        <?php elseif ($tab === 'social'): ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/social')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <?php foreach ($platforms as $platform => $meta): ?>
                        <div class="field">
                            <label for="social_<?= e($platform) ?>"><?= e($meta['label']) ?></label>
                            <input class="input" type="url" id="social_<?= e($platform) ?>" name="social_<?= e($platform) ?>"
                                   value="<?= e($social[$platform] ?? '') ?>" placeholder="https://">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save social links</button></div>
            </form>

        <?php elseif ($tab === 'sections'): ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/sections')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <p class="small muted">Turn sections on or off. A section with no content is hidden automatically.</p>
                    <?php foreach (App\Models\CardSection::DEFAULTS as $key => $label): ?>
                        <?php $row = $sectionMap[$key] ?? ['is_enabled' => 1]; ?>
                        <div class="row-between" style="padding:10px 0;border-bottom:1px solid var(--border)">
                            <div>
                                <div class="bold small"><?= e($label) ?></div>
                                <div class="tiny muted"><?= e($key) ?></div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enabled[]" value="<?= e($key) ?>" <?= (int) ($row['is_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <span class="track"></span>
                            </label>
                        </div>
                        <input type="hidden" name="order[]" value="<?= e($key) ?>">
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save sections</button></div>
            </form>

        <?php elseif ($tab === 'seo'): ?>
            <?php if (!$canSeo): ?>
                <div class="alert alert-info"><?= icon('award', 18) ?><div>SEO controls are available on paid plans. <a href="<?= e(url('billing')) ?>">Upgrade</a></div></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/seo')) ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="field">
                        <label for="seo_title">Page title</label>
                        <input class="input" type="text" id="seo_title" name="seo_title" value="<?= e(old('seo_title', (string) ($card['seo_title'] ?? ''))) ?>" maxlength="190" <?= $canSeo ? '' : 'disabled' ?>>
                        <div class="hint">Leave empty to generate it from your name and business.</div>
                    </div>
                    <div class="field">
                        <label for="seo_description">Meta description</label>
                        <textarea class="textarea" id="seo_description" name="seo_description" rows="3" maxlength="320" <?= $canSeo ? '' : 'disabled' ?>><?= e(old('seo_description', (string) ($card['seo_description'] ?? ''))) ?></textarea>
                    </div>
                    <div class="field">
                        <label for="seo_keywords">Keywords</label>
                        <input class="input" type="text" id="seo_keywords" name="seo_keywords" value="<?= e(old('seo_keywords', (string) ($card['seo_keywords'] ?? ''))) ?>" maxlength="255" <?= $canSeo ? '' : 'disabled' ?>>
                    </div>
                    <label class="checkbox">
                        <input type="checkbox" name="noindex" value="1" <?= !empty($settings['noindex']) ? 'checked' : '' ?> <?= $canSeo ? '' : 'disabled' ?>>
                        <span class="small">Hide this card from search engines</span>
                    </label>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit" <?= $canSeo ? '' : 'disabled' ?>><?= icon('save', 16) ?> Save SEO</button></div>
            </form>

        <?php else: ?>
            <form method="post" action="<?= e(url('cards/' . $cardId . '/settings')) ?>" class="card mb-3">
                <?= csrf_field() ?>
                <div class="card-body">
                    <?php foreach ([
                        'enquiry_enabled' => ['Enquiry form', 'Let visitors send you a message from the card.'],
                        'show_qr'         => ['QR code section', 'Show a scannable QR code on the card itself.'],
                        'vcard_enabled'   => ['Save contact button', 'Allow visitors to download your contact as a vCard.'],
                    ] as $key => [$label, $hint]): ?>
                        <div class="row-between" style="padding:11px 0;border-bottom:1px solid var(--border)">
                            <div>
                                <div class="bold small"><?= e($label) ?></div>
                                <div class="tiny muted"><?= e($hint) ?></div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= ($settings[$key] ?? true) ? 'checked' : '' ?>>
                                <span class="track"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-right"><button class="btn" type="submit"><?= icon('save', 16) ?> Save settings</button></div>
            </form>

            <form method="post" action="<?= e(url('cards/' . $cardId . '/slug')) ?>" class="card mb-3">
                <?= csrf_field() ?>
                <div class="card-header"><h3>Card link</h3></div>
                <div class="card-body">
                    <div class="input-group">
                        <span class="addon"><?= e(rtrim(url('card'), '/')) ?>/</span>
                        <input class="input" type="text" name="slug" value="<?= e((string) $card['slug']) ?>"
                               data-slug-check data-card-id="<?= $cardId ?>" data-slug-status="#slug-status" required>
                    </div>
                    <div class="hint" id="slug-status">Changing the link breaks any QR code already printed with the old address.</div>
                </div>
                <div class="card-footer text-right"><button class="btn btn-secondary" type="submit">Update link</button></div>
            </form>

            <div class="card mb-3">
                <div class="card-header"><h3>Duplicate</h3></div>
                <div class="card-body">
                    <p class="small muted">Create a copy of this card with all of its content — useful for a second branch or language.</p>
                    <form method="post" action="<?= e(url('cards/' . $cardId . '/duplicate')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary" type="submit"><?= icon('copy', 15) ?> Duplicate this card</button>
                    </form>
                </div>
            </div>

            <div class="card" style="border-color:var(--danger)">
                <div class="card-header"><h3 style="color:var(--danger)">Delete this card</h3></div>
                <div class="card-body">
                    <p class="small muted">This permanently removes the card, its content, analytics and leads. Type <code><?= e((string) $card['slug']) ?></code> to confirm.</p>
                    <form method="post" action="<?= e(url('cards/' . $cardId . '/delete')) ?>" data-confirm="Delete this card permanently? This cannot be undone.">
                        <?= csrf_field() ?>
                        <div class="row" style="gap:8px">
                            <input class="input flex-1" type="text" name="confirm_slug" placeholder="<?= e((string) $card['slug']) ?>" required>
                            <button class="btn btn-danger" type="submit"><?= icon('trash', 15) ?> Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body row" style="gap:10px;flex-wrap:wrap">
                <a class="btn btn-secondary btn-sm" href="<?= e(url('cards/' . $cardId . '/services')) ?>"><?= icon('zap', 15) ?> Services (<?= (int) $counts['services'] ?>)</a>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('cards/' . $cardId . '/products')) ?>"><?= icon('package', 15) ?> Products (<?= (int) $counts['products'] ?>)</a>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('cards/' . $cardId . '/gallery')) ?>"><?= icon('image', 15) ?> Gallery (<?= (int) $counts['gallery'] ?>)</a>
            </div>
        </div>
    </div>

    <aside class="editor-preview">
        <div class="card">
            <div class="card-header"><h3 style="font-size:.95rem">Live preview</h3>
                <button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('preview-frame').contentWindow.location.reload()"><?= icon('refresh', 14) ?></button>
            </div>
            <div class="card-body">
                <div class="phone-frame">
                    <iframe id="preview-frame" src="<?= e(url('cards/' . $cardId . '/preview')) ?>" title="Live preview"></iframe>
                </div>
                <?php if ($template !== null): ?>
                    <p class="tiny muted text-center mt-2"><?= e((string) $template['name']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
<?php $__view->stop(); ?>
