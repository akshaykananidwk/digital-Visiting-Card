<?php /** @var array<string,mixed> $values */ $__view->extend('layouts.install'); ?>
<?php $__view->start('content'); ?>
    <p class="muted">Enter the MySQL details from your hosting control panel. If the database does not exist yet we will try to create it.</p>

    <form method="post" action="<?= e(url_path('install/database')) ?>" id="db-form">
        <?= csrf_field() ?>

        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label class="required" for="host">Database host</label>
                <input class="input" type="text" id="host" name="host" value="<?= e(old('host', $values['host'])) ?>" required autocomplete="off">
                <div class="hint">Usually <code>localhost</code> or <code>127.0.0.1</code>.</div>
            </div>
            <div class="field">
                <label for="port">Port</label>
                <input class="input" type="number" id="port" name="port" value="<?= e(old('port', $values['port'])) ?>" min="1" max="65535">
            </div>
        </div>

        <div class="field">
            <label class="required" for="database">Database name</label>
            <input class="input" type="text" id="database" name="database" value="<?= e(old('database', $values['database'])) ?>" required autocomplete="off">
        </div>

        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label class="required" for="username">Database user</label>
                <input class="input" type="text" id="username" name="username" value="<?= e(old('username', $values['username'])) ?>" required autocomplete="off">
            </div>
            <div class="field">
                <label for="password">Database password</label>
                <input class="input" type="password" id="password" name="password" value="<?= e($values['password']) ?>" autocomplete="new-password">
            </div>
        </div>

        <div class="grid grid-2" style="gap:0 16px">
            <div class="field">
                <label for="prefix">Table prefix</label>
                <input class="input" type="text" id="prefix" name="prefix" value="<?= e(old('prefix', $values['prefix'])) ?>" placeholder="dvc_" autocomplete="off">
                <div class="hint">Optional — useful when sharing a database.</div>
            </div>
            <div class="field">
                <label class="required" for="app_url">Website address</label>
                <input class="input" type="url" id="app_url" name="app_url" value="<?= e(old('app_url', $app_url)) ?>" required>
                <div class="hint">Cards will be published under this address.</div>
            </div>
        </div>

        <div class="row mt-2" style="gap:10px">
            <button class="btn btn-secondary" type="button" id="test-btn" data-no-lock>Test connection</button>
            <button class="btn flex-1" type="submit">Save &amp; continue <?= icon('arrow-right', 16) ?></button>
        </div>
        <div id="test-result" class="mt-2"></div>
    </form>
<?php $__view->stop(); ?>

<?php $__view->start('scripts'); ?>
<script>
document.getElementById('test-btn').addEventListener('click', async function () {
  const form = document.getElementById('db-form');
  const out = document.getElementById('test-result');
  const btn = this;
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Testing…';
  out.innerHTML = '';
  try {
    const res = await fetch(form.action.replace('/database', '/test-connection'), {
      method: 'POST', body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await res.json();
    out.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'error') + '">' + (data.message || '') + '</div>';
  } catch (e) {
    out.innerHTML = '<div class="alert alert-error">Could not run the test. Please try saving instead.</div>';
  } finally {
    btn.disabled = false; btn.textContent = 'Test connection';
  }
});
</script>
<?php $__view->stop(); ?>
