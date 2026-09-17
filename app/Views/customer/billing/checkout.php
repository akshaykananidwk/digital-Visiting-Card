<?php
/** @var array<string,mixed> $plan @var array<string,mixed> $breakdown @var array<string,mixed> $user
 *  @var string $keyId @var bool $testMode */
$__view->extend('layouts.panel');
?>
<?php $__view->start('content'); ?>
<div class="container-sm" style="margin:0">
    <?php if ($testMode): ?>
        <div class="alert alert-warning"><?= icon('alert', 18) ?><div>The payment gateway is in <strong>test mode</strong>. No real money will be charged.</div></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Order summary</h2></div>
        <div class="card-body">
            <div class="row-between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                <div>
                    <div class="bold"><?= e((string) $plan['name']) ?> plan</div>
                    <div class="tiny muted"><?= (int) $plan['duration_days'] ?> days of access</div>
                </div>
                <strong><?= e(money((float) $breakdown['subtotal'])) ?></strong>
            </div>
            <?php if ((bool) $breakdown['gst_enabled']): ?>
                <div class="row-between" style="padding:9px 0;border-bottom:1px solid var(--border)">
                    <span class="small">GST (<?= e((string) $breakdown['tax_rate']) ?>%)</span>
                    <span><?= e(money((float) $breakdown['tax_amount'])) ?></span>
                </div>
            <?php endif; ?>
            <div class="row-between" style="padding:14px 0">
                <strong>Total payable</strong>
                <strong style="font-size:1.35rem"><?= e(money((float) $breakdown['total'])) ?></strong>
            </div>

            <?php if ((bool) $breakdown['gst_enabled']): ?>
                <div class="field">
                    <label for="gstin">Your GSTIN (optional)</label>
                    <input class="input" type="text" id="gstin" name="gstin" maxlength="20" value="<?= e((string) ($user['gstin'] ?? '')) ?>" placeholder="For an input-credit invoice">
                </div>
            <?php endif; ?>

            <button class="btn btn-lg btn-block" type="button" id="pay-button" data-plan="<?= e((string) $plan['slug']) ?>">
                <?= icon('credit-card', 18) ?> Pay <?= e(money((float) $breakdown['total'])) ?> securely
            </button>
            <div id="pay-status" class="mt-2"></div>

            <p class="tiny muted text-center mt-3">
                <?= icon('lock', 12) ?> Payments are processed by Razorpay. Your plan is activated only after the payment is verified on our server.
            </p>
        </div>
    </div>

    <p class="text-center small mt-3"><a href="<?= e(url('billing')) ?>">&larr; Back to billing</a></p>
</div>
<?php $__view->stop(); ?>

<?php $__view->start('scripts'); ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
  const button = document.getElementById('pay-button');
  const status = document.getElementById('pay-status');
  if (!button) return;

  const show = function (message, type) {
    status.innerHTML = '<div class="alert alert-' + type + '" style="margin:0">' + message + '</div>';
  };

  button.addEventListener('click', async function () {
    button.disabled = true;
    button.innerHTML = '<span class="spinner"></span> Preparing…';
    status.innerHTML = '';

    try {
      const gstField = document.getElementById('gstin');
      const created = await DVC.request('billing/order', {
        method: 'POST',
        body: new URLSearchParams({
          plan: button.dataset.plan,
          gstin: gstField ? gstField.value : '',
          _token: DVC.token
        })
      });

      if (!created.success) throw new Error(created.message || 'Could not start the payment.');

      const options = {
        key: created.key,
        amount: created.order.amount,
        currency: created.order.currency,
        name: created.name,
        description: created.description,
        order_id: created.order.id,
        prefill: created.prefill,
        theme: { color: '#4f46e5' },
        modal: {
          ondismiss: function () {
            button.disabled = false;
            button.innerHTML = 'Try payment again';
            show('Payment was cancelled. You can try again whenever you are ready.', 'warning');
          }
        },
        handler: async function (response) {
          button.innerHTML = '<span class="spinner"></span> Verifying payment…';
          show('Verifying your payment. Please do not close this page.', 'info');
          try {
            const verified = await DVC.request('billing/verify', {
              method: 'POST',
              body: new URLSearchParams({
                razorpay_order_id: response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature: response.razorpay_signature,
                _token: DVC.token
              })
            });
            if (verified.success && verified.redirect) {
              window.location.href = verified.redirect;
            } else {
              show(verified.message || 'We could not verify the payment.', 'error');
              button.disabled = false;
              button.innerHTML = 'Contact support';
            }
          } catch (err) {
            show(err.message + ' Your payment reference is ' + response.razorpay_payment_id + ' — please quote it when contacting support.', 'error');
            button.disabled = false;
          }
        }
      };

      const checkout = new Razorpay(options);
      checkout.on('payment.failed', function (response) {
        show('Payment failed: ' + (response.error && response.error.description ? response.error.description : 'unknown error'), 'error');
        button.disabled = false;
        button.innerHTML = 'Try payment again';
      });
      checkout.open();
      button.innerHTML = 'Complete the payment in the popup';
    } catch (error) {
      show(error.message || 'Something went wrong. Please try again.', 'error');
      button.disabled = false;
      button.innerHTML = 'Try again';
    }
  });
})();
</script>
<?php $__view->stop(); ?>
