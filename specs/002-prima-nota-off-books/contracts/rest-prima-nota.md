# Contract: Prima Nota REST (tag + verify)

Maps to MCP tools `espo.get` / `espo.update` (explore-espo-endpoints). Base: `{BASE}/api/v1`. Auth: `X-Api-Key`. Canonical entity type: **`PrimaNota`**.

## Describe (after deploy)

- `GET /api/v1/Metadata?key=entityDefs.PrimaNota`
- Confirm `fields.donationPaymentProvider.options` contains `DonorPocket`
- Confirm `fields.excludeFromDigitalReports.type` is `bool`

## Read one

```
GET /api/v1/PrimaNota/{id}?select=id,transactionDate,amount,amountOut,donationPaymentProvider,excludeFromDigitalReports,paymentStatus,description,subjectName,beneficiaryName
```

Always send `select`. `maxSize` N/A on get-by-id.

## Update platform (enum **key**, not label)

```
PUT /api/v1/PrimaNota/{id}
Content-Type: application/json

{"donationPaymentProvider":"DonorPocket"}
```

Do **not** send `excludeFromDigitalReports` (read-only; Formula sets `true` for `DonorPocket`). Strip other read-only fields.

### Expected 200

- `donationPaymentProvider` = `DonorPocket`
- `excludeFromDigitalReports` = `true`
- Row still GET-able (not cancelled, not deleted)

### Expected failures

| Status | Meaning | MCP mapping |
|--------|---------|-------------|
| 400 + `X-Status-Reason` / `messageTranslation` type valid | Enum key not on this server yet (deploy/rebuild missing) | `invalid_params` — wait for deploy |
| 400 platformImmutable | Stripe involved or hook not updated | `invalid_params` |
| 401 | Bad API key | stop |
| 403 | ACL | stop |
| 404 | Wrong id | `not_found` |

## Production ids (FR-010)

- `6a931f64acfeb45c6`
- `6a8c52804555b63c2`

Apply **only** after auto-deploy + rebuild verified via Metadata GET.
