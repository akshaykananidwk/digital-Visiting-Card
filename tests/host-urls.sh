#!/bin/bash
# Host handling: the site must follow hosts it recognises and must never echo
# an unrecognised Host header back into generated URLs.
CASE="$(cd "$(dirname "$0")" && pwd)/host-url-case.php"
pass=0; fail=0
check() { # desc appUrl host trusted expected
  local out
  out=$(php "$CASE" "$2" "$3" "$4" 2>&1 | tail -1)
  if [ "$out" = "$5" ]; then echo "  PASS  $1"; pass=$((pass+1));
  else echo "  FAIL  $1 → got '$out', want '$5'"; fail=$((fail+1)); fi
}

check "APP_URL host used verbatim"            "https://example.com" "example.com"      ""              "https://example.com/dashboard"
check "www counterpart is trusted"            "https://example.com" "www.example.com"  ""              "https://www.example.com/dashboard"
check "non-www counterpart is trusted"        "https://www.example.com" "example.com"  ""              "https://example.com/dashboard"
check "explicitly trusted alias is followed"  "https://example.com" "staging.example.com" "staging.example.com" "https://staging.example.com/dashboard"
check "unknown Host falls back to APP_URL"    "https://example.com" "evil.attacker.test" ""            "https://example.com/dashboard"
check "unknown Host w/ trusted list set"      "https://example.com" "evil.attacker.test" "staging.example.com" "https://example.com/dashboard"
check "port preserved on trusted host"        "http://localhost:8080" "localhost:8080"  ""             "http://localhost:8080/dashboard"
check "sibling host on a port is trusted"     "http://localhost:8080" "www.localhost:8080" ""          "http://www.localhost:8080/dashboard"

echo
echo "RESULT: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
