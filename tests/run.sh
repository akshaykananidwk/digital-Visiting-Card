#!/bin/bash
#
# Runs every suite and reports a single pass/fail.
#
#   BASE_URL       the running site (default http://127.0.0.1:8080)
#   CHROMIUM_PATH  Chromium binary for the browser suites (optional)
#   TEST_EMAIL     customer login used by the browser suites
#   TEST_PASSWORD  its password
#
# The PHP suites talk to the configured database directly and write test rows,
# so point them at a development installation, never production.

set -u
cd "$(dirname "$0")/.."

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
export BASE_URL

failed=0
run() {
    local name="$1"; shift
    echo
    echo "=== $name ==="
    if "$@"; then
        echo "--- $name OK"
    else
        echo "--- $name FAILED"
        failed=$((failed + 1))
    fi
}

run "Host and URL generation" bash tests/host-urls.sh
run "Security headers"        bash tests/security-headers.sh
run "Payments"                php tests/payments.php
run "Webhooks"                php tests/webhooks.php
run "QR encoder"              php tests/qr.php
run "Health check"            php bin/console.php health

if command -v node >/dev/null 2>&1 && [ -d node_modules/playwright-core ] || [ -n "${CHROMIUM_PATH:-}" ]; then
    run "Responsive layout" node tests/browser/responsive.js
    run "Preview framing"   node tests/browser/framing.js
else
    echo
    echo "=== Browser suites skipped (playwright-core / CHROMIUM_PATH not available) ==="
fi

echo
if [ "$failed" -eq 0 ]; then
    echo "ALL SUITES PASSED"
else
    echo "$failed SUITE(S) FAILED"
fi
exit "$failed"
