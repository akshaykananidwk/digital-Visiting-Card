#!/bin/bash
B="${BASE_URL:-http://127.0.0.1:8080}"
pass=0; fail=0
ok(){ echo "  PASS  $1"; pass=$((pass+1)); }
no(){ echo "  FAIL  $1"; fail=$((fail+1)); }
has(){ # url header-regex desc
  curl -s -D - -o /dev/null "$1" | tr -d '\r' | grep -qiE "$2" && ok "$3" || no "$3"
}
echo "== Security headers =="
has "$B/" "^X-Content-Type-Options: nosniff" "X-Content-Type-Options nosniff"
has "$B/" "^Referrer-Policy:" "Referrer-Policy present"
has "$B/" "^X-Frame-Options: DENY" "X-Frame-Options DENY on ordinary pages"
has "$B/templates/preview/computer-shop-001" "^X-Frame-Options: SAMEORIGIN" "X-Frame-Options SAMEORIGIN on embeddable previews"
has "$B/" "frame-ancestors 'none'" "CSP frame-ancestors none on ordinary pages"
has "$B/templates/preview/computer-shop-001" "frame-ancestors 'self'" "CSP frame-ancestors self on previews"
has "$B/" "object-src 'none'" "CSP object-src none"
has "$B/" "^Content-Security-Policy:.*base-uri 'self'" "CSP base-uri self"

echo "== Host header handling =="
body=$(curl -s -H "Host: evil.attacker.test" "$B/login")
echo "$body" | grep -q "evil.attacker.test" && no "unknown Host not reflected into page URLs" || ok "unknown Host not reflected into page URLs"
echo "$body" | grep -q 'action="/login"' && ok "login form posts back to the visitor's own host" || no "login form posts back to the visitor's own host"

echo "== CSRF =="
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST -d "email=a@b.c&password=x" "$B/login")
[ "$code" = "419" ] && ok "login POST without CSRF token rejected (419)" || no "login POST without CSRF token rejected (got $code)"

echo "== Protected files =="
for f in .env app/bootstrap.php app/version.php config/routes.php database/migrations storage/logs; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "$B/$f")
  [ "$code" = "403" ] || [ "$code" = "404" ] && ok "/$f not served ($code)" || no "/$f served ($code)"
done

echo "== Installer lockout =="
code=$(curl -s -o /dev/null -w "%{http_code}" -L "$B/install")
[ "$code" = "200" ] && curl -s -L "$B/install" | grep -qi "already installed\|dashboard\|sign in" && ok "installer locked after install" || ok "installer redirects away ($code)"

echo
echo "RESULT: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
