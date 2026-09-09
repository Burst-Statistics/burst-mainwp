# Admin App feature checklist

Copy this checklist when adding or extending dashboard functionality. See [AGENTS.md](../AGENTS.md) and [DESIGN_PHILOSOPHY.md](DESIGN_PHILOSOPHY.md).

## Checklist

1. **Product** — Route/menu id matches `config/menu.php` (and capabilities).
2. **Route** — `src/routes/{id}.jsx` with `createFileRoute`; `shouldLoadRoute` in loader.
3. **UI** — `PageHeader` + `ErrorBoundary` + domain block(s); pass [design checklist](DESIGN_PHILOSOPHY.md#ui-checklist).
4. **Data** — `src/api/get{Name}.ts` (or `.js`) → `getData('{type}', …)`; add type to `src/types/api-endpoints.ts` if new.
5. **PHP** — Handler in `class-app.php` `get_data()` switch, `burst_get_data` filter, or Statistics class.
6. **Filters / date** — `useBlockConfig`, `useDateRange`, filter stores.
7. **i18n** — All user-visible strings via `__()` / `_e()`.
8. **Formatting** — Grep [`formatting.ts`](../src/utils/formatting.ts); reuse or extend there only.
9. **CSS** — Tailwind utilities; run `npm run build:css` if new classes.
10. **Verify** — `npm run lint`, `npm run build`, relevant e2e spec.

## UI design gates

From [DESIGN_PHILOSOPHY.md](DESIGN_PHILOSOPHY.md):

- [ ] Every visible element helps a decision
- [ ] Primary metric/message is visually dominant
- [ ] Titles are conclusion-first where possible
- [ ] Secondary detail uses tooltip/popover/wizard
- [ ] Design-system colors only
- [ ] Values use `formatting.ts`

## Reference

| Step | Example |
| ---- | ------- |
| Route | `src/routes/statistics.jsx` |
| Block + query | `src/components/Statistics/InsightsBlock.js` |
| API wrapper | `src/api/getInsightsData.ts` |
| PHP data | `Statistics::get_insights_data()` |
