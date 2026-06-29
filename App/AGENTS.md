# Admin App — agent guide

WordPress admin dashboard SPA (`includes/Admin/App/`). React + TanStack Router + React Query + Zustand + Tailwind, enqueued from `class-app.php`.

## Commands

Run from `includes/Admin/App/`:

| Command | Purpose |
| ------- | ------- |
| `npm run start` | Webpack dev (wp-scripts) |
| `npm run build` | Production bundle → `build/` |
| `npm run build:css` | Compile Tailwind → `src/tailwind.generated.css` |
| `npm run build:css:watch` | Watch Tailwind (run alongside `start`) |
| `npm run lint` / `lint:fix` | ESLint (includes `react-compiler`) |

After UI/CSS changes: run `build:css` and commit `tailwind.generated.css` when new utilities were added.

## Do not edit

- `src/routeTree.gen.ts` — TanStack Router codegen
- `src/tailwind.generated.css` (+ `.map`) — PostCSS output
- `build/*` — webpack output

## Directory map

| Path | Role |
| ---- | ---- |
| `src/routes/` | File-based TanStack routes; `shouldLoadRoute` in `src/utils/helper.tsx` |
| `src/components/` | UI by domain (`Statistics/`, `Dashboard/`, `Reporting/`, …) |
| `src/api/` | Thin wrappers around `getData` / `getDatatableData` |
| `src/utils/api.js` | REST client (also used by Dashboard Widget — avoid breaking relative imports) |
| `src/utils/formatting.ts` | **Canonical formatting** — check before any display logic |
| `src/store/` | Zustand stores |
| `src/hooks/` | Shared hooks (`useBlockConfig`, `useDateRange`, …) |
| `config/*.php` | Menu + settings fields (PHP + React Settings) |

## Conventions (new code)

- Prefer `.ts` / `.tsx`; match neighbors when editing legacy `.js`
- Imports: `@/` alias; `@wordpress/i18n` for strings; REST via `api.js`
- UI: Tailwind + `Block` / `BlockHeading` / `BlockContent`
- Data: React Query in components; `src/api/getX` calling `getData(type, …)`
- **Formatting:** grep `src/utils/formatting.ts` first — no duplicate `toLocaleString` / `Intl` in components
- Router: add `src/routes/{name}.jsx` with `createFileRoute`; rebuild to regenerate `routeTree.gen.ts`
- React: hooks from `react`; `createRoot` from `@wordpress/element` in `index.tsx` (webpack externals) — do not mix inconsistently
- Use file per component structure where applicable.

## PHP boundary

- Statistics: `GET burst/v1/data/{type}` → `App::get_data()` in `class-app.php` (switch + `burst_get_data` filter)
- Ecommerce: `GET burst/v1/data/ecommerce/{type}` (Pro)
- Datatables: `GET burst/v1/data/datatable/{id}` or `data/ecommerce/datatable/{id}`
- Settings: `fields/get`, `fields/set` + `config/fields.php`
- New metrics/blocks usually need **PHP handler + `src/api` + component**

Types for `getData()` are documented in `src/types/api-endpoints.ts`.

## Design & UI

Read **[docs/DESIGN_PHILOSOPHY.md](docs/DESIGN_PHILOSOPHY.md)** before UI work.

- Clarity over decoration; remove non-decision UI
- Hierarchy via space, weight, size — gray foundation (`text-text-*`, `gray-*`)
- Active, conclusion-first block titles
- Progressive disclosure (`HelpTooltip`, tooltips, popovers, modals, DataTableOverlay, wizards)
- Honest charts; Nivo for new visualisations

## New features

Use **[docs/FEATURE_TEMPLATE.md](docs/FEATURE_TEMPLATE.md)**.

## Reference implementations

| Pattern | Files |
| ------- | ----- |
| Route + blocks | `src/routes/statistics.jsx` |
| Query + API + store | `InsightsBlock.js`, `getInsightsData.ts` |
| Settings field | `config/fields.php`, `Field.jsx` |
| Stat + explanation | `ExplanationAndStatsItem.js` |
| Formatting | `src/utils/formatting.ts` |

## Testing

- E2E: `tests/e2e/specs/hasDashboard.spec.js`, reporting, goals specs. Suggest to add e2e tests after creating something.
- No JS unit tests in App today; PHP changes may need `tests/phpunit/`
