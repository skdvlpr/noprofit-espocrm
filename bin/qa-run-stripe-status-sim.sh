#!/usr/bin/env bash
# Host orchestrator: snapshot CRM dashlets → Stripe status sim → snapshot → assert.
set -euo pipefail

CRM_ROOT="${SAFEHOUSE_CRM_ROOT:-/home/skoksharov/nonprofit-espocrm}"
SITE_ROOT="${SAFEHOUSE_SITE_ROOT:-/home/skoksharov/safehouse-community-site}"
DRY_ARG="${1:-}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

summary() {
  (cd "$CRM_ROOT" && ddev exec php bin/print-prima-nota-summary.php) | sed -n '/^{/,$p'
}

echo "=== BEFORE ==="
summary | tee "$TMP/before.json"

echo ""
echo "=== RUN SITE SIM ${DRY_ARG} ==="
(cd "$SITE_ROOT" && ddev exec php bin/qa-stripe-prima-nota-status-sim.php ${DRY_ARG}) | tee "$TMP/sim.txt"

if [[ "$DRY_ARG" == "--dry-run" ]]; then
  exit 0
fi

echo ""
echo "=== AFTER ==="
summary | tee "$TMP/after.json"

grep '^EXPECT_JSON=' "$TMP/sim.txt" | tail -1 | sed 's/^EXPECT_JSON=//' > "$TMP/expect.json"

python3 - "$TMP/before.json" "$TMP/after.json" "$TMP/expect.json" <<'PY'
import json, sys
before = json.load(open(sys.argv[1]))
after = json.load(open(sys.argv[2]))
expect = json.load(open(sys.argv[3]))
payout = float(expect["payoutNet"])
planned_drop = float(expect["plannedDrop"])
cash_d = round(float(after["cashBalance"]) - float(before["cashBalance"]), 2)
in_d = round(float(after["month"]["amountIn"]) - float(before["month"]["amountIn"]), 2)
pin_d = round(float(after["month"]["plannedAmountIn"]) - float(before["month"]["plannedAmountIn"]), 2)
print("--- DELTAS ---")
print(f"cashBalance Δ {cash_d} (expected ~+{payout})")
print(f"month.amountIn Δ {in_d} (expected ~+{payout})")
print(f"month.plannedAmountIn Δ {pin_d} (expected ~-{planned_drop})")
ok = (
    abs(cash_d - payout) < 0.05
    and abs(in_d - payout) < 0.05
    and abs(pin_d + planned_drop) < 0.05
)
print(("PASS" if ok else "FAIL") + " dashlet deltas vs expected")
# Also print target ids for CRM spot-check
print("payoutIds", expect.get("payoutIds"))
print("refundIds", expect.get("refundIds"))
print("cancelIds", expect.get("cancelIds"))
sys.exit(0 if ok else 1)
PY
