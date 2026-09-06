# Contract: Prima Nota digital money totals

**Consumer**: `PrimaNotaStatsProvider` and any future dashboard/report that answers “organisation digital/bank position”.

## One-sentence rule (SC-005)

Count a Prima Nota row in digital realised totals only if it is **not** excluded from digital reports **and** payment status is Inviato or empty; count it in digital forecast only if it is **not** excluded **and** status is Planned.

Do **not** name payment platforms in new aggregations.

## Where clauses (Espo search params)

### Digital channel (replaces “not Cash”)

```php
[
    'OR' => [
        ['excludeFromDigitalReports!=' => true],
        ['excludeFromDigitalReports' => null],
    ],
]
```

### Realised (`incomeCountedWhere`)

`AND` of: status Inviato **or** null; digital channel.

### Planned (`plannedCountedWhere`)

`AND` of: status Planned; digital channel.

## Surfaces that MUST use this contract (SC-006)

- Saldo digitale (`bankBalance`)
- Period banners (today / month / year `amountIn` / `amountOut` / `managementBalance`)
- Planned metrics on those periods
- List-page reporting totals that call `getTotals()` / `getSummary()`

## Non-goals

- Prima Nota **list** is not filtered by this contract (all non-deleted rows remain visible).
- Soft-deleted rows stay out via Espo `deleted` as today.
