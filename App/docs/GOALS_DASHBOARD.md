# Goals dashboard: development instructions

Turn the Goals page from a settings table into a dashboard: a list of goals on the left, the selected goal's results on the right. Creating and editing goals keeps the existing goal wizard as it is.

Read first: `AGENTS.md` (repo root), `includes/Admin/App/AGENTS.md`, `includes/Admin/App/docs/DESIGN_PHILOSOPHY.md`, `includes/Admin/App/docs/FEATURE_TEMPLATE.md`.

## Scope

In scope:
- New layout of the Goals page (list and detail).
- Routing per goal with TanStack Router: `/goals` and `/goals/$goalId`.
- One new data type for per-goal totals, comparison and health.
- A goal health check, shown in the list, in the goal detail and as a task in the existing notifications.
- Pro gating of the breakdown blocks.

Out of scope (do not touch):
- The goal wizard (create and edit UI). Reuse it through `useGoalWizardStore`.
- The main menu and header navigation.
- The Overview dashboard `GoalsBlock`. It must keep working unchanged.
- Funnels, matrix views or any other concept not described here.

## Layout

Two columns, using the same pattern as the Settings and Reporting pages (left sub navigation card, content on the right). At narrow widths the list stacks above the detail.

```
┌ PageHeader (date range picker only, no "Add filter") ──────────────────────┐
├──────────────────────┬─────────────────────────────────────────────────────┤
│ Goals   [New goal]   │ Header card                                         │
│                      │  name · type chip · what it tracks   [Active][Edit] │
│ ▣ All goals   1,284  │  Conclusion-first title                             │
│   Overview of 5 ↑18% │  (health notice, only when the goal is not counting)│
│ ──────────────────── │  Conversions | Converting visitors | Conversion rate│
│ Your goals           │  Trend chart: this period vs previous period        │
│ ▌Purchase com…  412  │                                                     │
│  Page visit · … ↑24% ├─────────────────────────────────────────────────────┤
│  Click purchase 538  │ All goals: "Goals compared" block                   │
│  …                   │ Single goal: Sources | Entry pages (Pro)            │
│  Click on WP… ⚠ 167  │                                                     │
│  Not counting since… │                                                     │
│ 5 of 5 goals active  │                                                     │
└──────────────────────┴─────────────────────────────────────────────────────┘
```

### Left: goal list (`GoalsNav`)

- Card with heading "Goals" and the "New goal" button. The button calls `openWizard()` and respects `useIsGoalLimitReached` and `GoalLimitNotice` exactly like `GoalsHeaderControls` does today.
- First item: **All goals**. It must look different from a single goal: a small square icon tile (four-squares grid icon) in a light green tile, a slightly larger title, and the subtitle "Overview of {n} goals". Right side: total conversions and change.
- A small gray label "Your goals" and a top border separate All goals from the goal items.
- Goal items: title (truncate), subtitle `{type label} · {what it tracks}` (truncate, reuse `goalText.ts` for the tracking description), right side conversions and change versus the previous period.
- Inactive goals stay in the list with muted text and an "Inactive" chip, sorted after active goals.
- A goal flagged by the health check shows an amber line under its subtitle: warning icon + "Not counting since {date}".
- Footer text: "{active} of {total} goals active".
- Selected state: identical to the `SubNavigation` active item (light gray background, 4px green left border, green semibold title). Reuse its classes, do not invent a new selection style.
- Each item is a TanStack `<Link>` (not a button with an onClick), so it gets real hrefs, `aria-current`, middle-click and keyboard support. Keep search params (date range) when navigating.

### Right: header card (`GoalHeader`)

- Single goal: top line shows the goal name (green, semibold), the type chip and what it tracks. Right side: the existing active toggle (reuse the logic from `GoalStatusCell` and `toggleGoalStatus` from `useGoalsData`) and a labeled secondary button "Edit goal" with a pencil icon that calls `openWizard( goal.id )`. Put delete in a small overflow menu next to Edit that opens the existing `DeleteGoalModal`.
- All goals: top line "All goals" + "{n} active goals combined". No toggle, no edit.
- Title: conclusion-first, one sentence. Build it in a pure helper (`getGoalDetailTitle.ts`, with unit tests) from the data, for example:
  - change ≥ +5%: "Conversions up {x}%, led by {top goal}" (All goals) / "Conversions up {x}%" (single goal)
  - change ≤ −5%: "Conversions down {x}%"
  - otherwise: "Conversions steady compared to the previous period"
  - not counting: "Not counting since {date}: a tracking problem, not a traffic one"
  - no conversions yet: "No conversions yet in this period"
- Health notice (single goal, only when flagged): one amber row inside the header card with the explanation "No conversions since {date}, while its page still gets visitors. The element or page may have changed." and a button "Check the goal" that calls `openWizard( goal.id, GOAL_STEP.TARGET )`.
- KPI row: Conversions (with change vs previous period), Converting visitors, Conversion rate. The primary number dominates (largest weight). Values through `src/utils/formatting.ts` only.
- Trend chart: conversions per day or week (follow the interval logic `InsightsBlock` already uses for the date range), current period as a solid green line, previous period as a dashed gray line. Use Nivo as `InsightsBlock` does. Data: `getInsightsData` with `metrics: ['goal_conversions']`, `compare_mode` for the previous period, and `filters: { goal_id }` with a goal id or `all`. This metric counts by each goal's `conversion_metric` and page like the KPI row; `conversions` counts every completion. Unique visitors are per interval, so a visitor converting on two days counts on both days but once in the KPI.

### Right, All goals: "Goals compared" block

- Shown only on `/goals`.
- One row per goal: name, a horizontal bar for conversions (scaled to the largest goal), conversions, conversion rate, change. The bar of a goal flagged by the health check is amber.
- Each row is a `<Link>` to `/goals/$goalId`.
- Free for everyone.

### Right, single goal: breakdowns (Pro)

Reuse the existing blocks with a fixed goal filter. Do not build new list components.

```jsx
<DataTableBlock allowedConfigs={[ 'referrers' ]} id="goal_referrers" customFilters={{ goal_id: String( goalId ) }} />
<DataTableBlock allowedConfigs={[ 'pages' ]} id="goal_pages" customFilters={{ goal_id: String( goalId ) }} />
```

- Default the datatable columns to the conversion columns (`conversions`, plus `conversion_rate` where the config has it) and sort by conversions.
- No devices block for now: `DevicesBlock` with a `goal_id` filter counts every hit in converting sessions, not conversions per device. It needs its own per-device conversion query first.
- Free users see these blocks behind the existing Pro upsell overlay. Follow the pattern in `FormsBlock.tsx` / `OutgoingLinksBlock.tsx` (`Upsell/OverlayBlock`). Do not add a new upsell component.
- Use conclusion-first block titles where the block supports a custom title, for example "{source} converts best at {rate}".

## Routing (TanStack Router)

Every goal already has a numeric `id` (`Goal.id` in `goalHelpers.ts`). Use it in the URL.

- `src/routes/goals.jsx` becomes the layout route. It keeps the existing `loader` with `shouldLoadRoute( 'goals', context.menus )` and `notFoundComponent`. Its component renders the two-column layout: `GoalsNav` on the left and `<Outlet />` on the right, plus the `GoalWizard` (moved here from `GoalsPage`).
- `src/routes/goals.index.jsx` (`/goals/`): All goals detail.
- `src/routes/goals.$goalId.jsx` (`/goals/$goalId`): single goal detail.
  - Validate the param: if it is not a number, or no goal with that id exists once the goals have loaded, show `NotFoundModal` with a link back to `/goals`. Do not redirect silently.
  - Pass the id as a string into filters (filters are string based, see `normalizeFilterValue`).
- Do not edit `routeTree.gen.ts`. Rebuild to regenerate it.
- Add `/goals` to `DATE_RANGE_ENABLED_ROUTES` in `src/utils/routeUtils.ts`, so the date range lives in the URL and survives switching goals. Do **not** add it to `FILTER_ENABLED_ROUTES`: the goal comes from the route param, and v1 has no other filters on this page.
- After creating a goal in the wizard, navigate to `/goals/{newId}`. After deleting the selected goal, navigate to `/goals` with `replace: true`.
- Update every link that points to the old Goals page if a better target now exists (for example a "view goal" link on the Overview `GoalsBlock` can go to `/goals/$goalId`). Keep `useFilterByGoal` as it is: it opens Insights filtered by a goal, which is still valid.
- If the shareable link / share token flow covers this route, make sure `/goals/$goalId` works with a share token like the other dashboards.

## Data

### Goal metadata

Keep using `useGoalsData` (`goals/get`) for titles, types, status, selectors and the CRUD actions.

### New data type: `goals_overview` (free)

Follow "Adding a data type" in `includes/Admin/App/AGENTS.md`:

1. PHP: a `case 'goals_overview'` in `App::get_data()` calling a new method on `Goal_Statistics` (`get_goals_overview( $args )`). Capability `view_burst_statistics`. If review requires a datatable id per the review guidelines, register it in `App::get_datatable_config()` with an explicit capability.
2. `src/api/getGoalsOverview.ts`
3. React Query hook `useGoalsOverview( startDate, endDate )`, shared by the list, the header and Goals compared.
4. Type in `src/types/api-endpoints.ts`.

Args: `date_start`, `date_end` (the comparison period is the same length directly before it, like `compare` uses).

Response:

```ts
type GoalsOverview = {
  totals: {
    conversions: number;              // sum of all active goals
    conversions_previous: number;
    converting_visitors: number;      // unique visitors with at least one conversion on any goal
    converting_visitors_previous: number;
    conversion_rate: number;          // converting_visitors / visitors in the period, in %
    conversion_rate_previous: number;
  };
  goals: Array<{
    id: number;
    conversions: number;
    conversions_previous: number;
    converting_visitors: number;
    conversion_rate: number;          // converting_visitors / visitors in the period, in %
    health: {
      status: 'ok' | 'not_counting';
      last_conversion: number | null; // unix timestamp
    };
  }>;
};
```

Rules:
- **All goals counting:** `conversions` is the sum across goals. `converting_visitors` counts each visitor once, even if they converted on several goals. The All goals rate uses unique visitors, so overlapping goals do not inflate it.
- Respect each goal's `conversion_metric` (visitors, sessions, pageviews) the same way `get_goals_data()` already does. Reuse its SQL helpers (`get_goal_completed_count_sql()` and friends) instead of writing new counting logic.
- One query grouped by `goal_id` for the current period and one for the previous period. No query per goal. Use the existing query timeout (`get_goal_query_timeout_ms()`).
- Skip inactive goals in the totals but still return their row.

### Health check

A goal is `not_counting` when all of these are true:
- The goal is active, and was created and last activated more than 14 days ago (`date_created` and `date_start`).
- It has 0 conversions in the last 14 days (relative to now, independent of the selected date range).
- It had at least 8 conversions in the 28 days up to its last conversion. A goal that converts less often is regularly quiet for two weeks while its tracking works.
- Its page still had pageviews in the last 14 days: the goal's `url` for page-specific goals, any pageview on the site for whole-site goals (`url` = `*`).

`last_conversion` is the timestamp of the latest conversion. Cache the health result per goal for 1 hour (transient or object cache), since it does not depend on the selected date range. A failed or timed out query is not cached and leaves the task as it is.

No page fetching or selector checking in this version.

### Notification

Add a task in `includes/Admin/App/config/tasks.php` / `class-tasks.php` that shows when at least one goal is `not_counting`: "{goal title} stopped counting on {date}" with a link to `/goals/{id}`. Evaluate it in the same place other tasks are evaluated, and reuse the health method. Don't run it on every request. A fresh health check schedules the task validation when the task no longer matches the goals. Dismissing the task holds for that goal until it has a new last conversion; the goal's own warning stays.

## Replace the old table

- `GoalsPage`, `GoalsList` and `GoalsTable` are replaced by the new layout. Remove code that is no longer used (no dead files). Move what is still useful (status toggle cell, delete modal, empty state, limit notice) into the new components.
- Empty state (no goals): keep `GoalsEmptyState` in the right column and an empty list on the left with only the "New goal" button.

## Components (one per file, `.tsx`)

`src/components/Goals/Dashboard/`:
- `GoalsLayout.tsx`: two-column layout used by the `goals.jsx` route
- `GoalsNav.tsx`, `GoalsNavItem.tsx`, `GoalsNavAllItem.tsx`
- `GoalHeader.tsx`, `GoalHealthNotice.tsx`, `GoalKpis.tsx`, `GoalTrendChart.tsx`
- `GoalsCompared.tsx`
- `GoalBreakdowns.tsx` (the three Pro blocks plus upsell)
- `getGoalDetailTitle.ts` + `getGoalDetailTitle.test.ts` (add to the test list in `package.json`)

## Styling and copy

- Tailwind tokens only (`text-text-black`, `text-text-gray`, `bg-white`, `border-gray-200`, the green from the theme). Amber warning colors from the existing notice or warning tokens. No raw hex values.
- Use `Block`, `BlockHeading` and `BlockContent` for the cards.
- Every string through `__( '…', 'burst-statistics' )`. Sentence case, no em dashes, UI copy register from `guidelines/voice.md`.
- Numbers, percentages, changes and dates through `src/utils/formatting.ts`.
- Touch targets at least 44px. The list must work with keyboard only.

## Done when

- [ ] `/goals` shows All goals selected; `/goals/12` shows goal 12 selected; reloading and the back button keep the selection.
- [ ] Changing the date range keeps the selected goal and updates every number.
- [ ] New goal, Edit goal, toggle and delete work through the existing wizard and modal, and the list updates without a reload.
- [ ] A goal matching the health rules shows the warning in the list, in the header card, and as a task.
- [ ] Free users see the breakdowns behind the upsell overlay; Pro users see real data.
- [ ] Overview `GoalsBlock` and Insights goal filter still work.
- [ ] `npm run lint`, `npm run test`, `npm run build` pass; PHPUnit test for `get_goals_overview()` (totals, unique visitors, previous period, health rules); e2e spec under `tests/e2e/goals/` covering navigation between goals and the not-found case.
- [ ] `includes/Admin/App/config/dashboard-features.php` (and the menu `description` if it changes) describe the new Goals dashboard, so the AI assistant describes it correctly.
