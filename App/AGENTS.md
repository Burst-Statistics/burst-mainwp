# Admin App — agent guide

WordPress admin dashboard SPA (`includes/Admin/App/`). React + TanStack Router + React Query + Zustand + Tailwind, enqueued from `class-app.php`.

## Commands

Run from `includes/Admin/App/`:

| Command | Purpose |
| ------- | ------- |
| `npm run start` | Webpack dev (wp-scripts) |
| `npm run build` | Production bundle → `build/` (runs `build:css` afterwards) |
| `npm run build:css` | Compile Tailwind → `build/tailwind.generated.css` |
| `npm run build:css:watch` | Watch Tailwind (run alongside `start`) |
| `npm run lint` / `lint:fix` | ESLint (includes `react-compiler`) |
| `npm run test` | TypeScript unit tests (`tsx --test`, list in `package.json`) |

After UI/CSS changes: run `build:css` to refresh the stylesheet. `build/` is gitignored, so the generated CSS is never committed; every build and release pipeline regenerates it.

## Do not edit

- `src/routeTree.gen.ts` — TanStack Router codegen
- `build/*` — webpack output and `tailwind.generated.css` (+ `.map`, PostCSS output)

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
- `index.tsx` wraps the app in `StyleSheetManager` to filter react-data-table props (`right`, `grow`, …). Preserve it when touching tables

## Styling and i18n

- Tailwind tokens from `src/styles/theme/tokens.css` and `tailwind.config.mjs`. Text: `text-text-black`, `text-text-gray`, `text-text-gray-light`. Surfaces: `bg-white`, `bg-gray-50`, `border-gray-200`
- No ad hoc hex values, `slate-*` or `zinc-*`
- Every string goes through `__( 'Visitors', 'burst-statistics' )` from `@wordpress/i18n`. Never hardcode user-facing text
- Wording follows the "UI copy" register in `guidelines/voice.md` (repo root): sentence case, conclusion-first titles, plain words, no em dashes

## PHP boundary

- Statistics: `GET burst/v1/data/{type}` → `App::get_data()` in `class-app.php` (switch + `burst_get_data` filter)
- Ecommerce: `GET burst/v1/data/ecommerce/{type}` (Pro)
- Datatables: `GET burst/v1/data/datatable/{id}` or `data/ecommerce/datatable/{id}`
- Settings: `fields/get`, `fields/set` + `config/fields.php`
- `get_data()` switch handles the core types. Other types use the `burst_get_data` filter, often from Pro in `includes/Pro/Admin/Statistics/class-statistics.php`

Adding a data type:

1. PHP handler (switch case or `burst_get_data` filter)
2. `src/api/get*.ts` wrapper
3. Component with React Query
4. Type in `src/types/api-endpoints.ts`

Types for `getData()` are documented in `src/types/api-endpoints.ts`.

## Design & UI

Read **[docs/DESIGN_PHILOSOPHY.md](docs/DESIGN_PHILOSOPHY.md)** before UI work.

- Clarity over decoration; remove non-decision UI
- Hierarchy via space, weight, size — gray foundation (`text-text-*`, `gray-*`)
- Active, conclusion-first block titles
- Progressive disclosure (`HelpTooltip`, tooltips, popovers, modals, DataTableOverlay, wizards)
- Honest charts: flat 2D, colors from the domain config, Nivo for new visualisations. No 3D or chartjunk

Before merging UI work:

- [ ] Every element helps a decision
- [ ] The primary metric dominates the block
- [ ] Numbers, dates and currency use `src/utils/formatting.ts`
- [ ] Every string uses `__()`

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
- Unit: `npm run test` runs the `*.test.ts` files listed in `package.json`. Add new test files to that list
- PHP changes may need `tests/phpunit/`
