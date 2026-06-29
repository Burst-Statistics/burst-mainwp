# Burst Statistics design philosophy

Burst Statistics is built on a simple premise: analytics should provide clarity, not overwhelm. We do not believe in vanity metrics, cluttered dashboards, or tracking users simply because we can. Our goal is to build a privacy-first, locally-hosted WordPress analytics tool that respects both the website owner and the website visitor.

This document outlines the core principles that guide our design, engineering, and product decisions. The second section maps those principles to concrete patterns in the Admin App React codebase.

---

## Core principles

### Respect focus through visual restraint

Every element on the screen demands cognitive energy, so we do not waste our users' time with decorative charts or inefficient UI. We design for deep focus. To achieve this, we rely heavily on ten specific shades of gray to build the foundation of our interface. The most important data point on the screen is visible through more visual weight e.g. white space, font-weight and size or in some cases color. For visualisations, we can use multiple colours if that makes the information clearer. If an element does not immediately help the user make a decision, it is removed.

### Solve the root problem, do not just build features

More features do not equal a better user experience. When users ask for new capabilities, our default answer is not to just add another button. Instead, we conduct customer interviews to identify the underlying friction they are experiencing. We build our product on the absolute rule of quality over quantity. We will always choose to do one meaningful thing thought through rather than ten things without thinking.

### Privacy is architecture, not a marketing toggle

We are fundamentally an ethical software product. Because Burst is locally-hosted, data ownership remains completely with the user. We do not build dark patterns or encourage invasive tracking. Ethical engineering means making the right thing the easiest thing to do.

### Tell the hard truth with data

Raw data is just noise until it is given context. We design our dashboards to tell a specific story.

- We use active titles that state clear conclusions rather than passive, generic labels.
- We categorize data logically, cutting through the noise of ambiguous metrics like "direct" traffic.
- We never sugarcoat numbers with deceptive, pretty visualizations like 3D pie charts. We use the most practical chart to communicate reality, even if that reality is uncomfortable.

### Empathy through progressive disclosure

Analytics can be deeply intimidating. To show empathy to our users, we do not throw all our data at them at once. Instead, we structure our interface to guide them naturally. We lean on progressive disclosure, using shepherds and clear explainer content to introduce complex information only when the user is ready for it.

### Engineering in service of simplicity

As a tool built with React, Tailwind, and PHP, we care deeply about clean code structure and performance. However, technical complexity should never bleed into the interface. The backend logic can be complex, but the frontend must always feel structural, clear, and effortless.

---

## Agent implementation guide

Maps product principles to code in `includes/Admin/App/`.

| Principle | Do in Admin App | Avoid |
| --------- | --------------- | ----- |
| Visual restraint | Reuse `Block`, `BlockHeading`, `BlockContent`; `bg-white`, `border-gray-200`, subtle shadows | New card styles, gradients, heavy borders, icon-only metrics without labels |
| Gray foundation | `bg-gray-50`/`100`, `text-text-black`, `text-text-gray`, `text-text-gray-light` (see `src/styles/theme/tokens.css`) | Random hex colors, Tailwind `slate`/`zinc`, rainbow chrome |
| Visual hierarchy | One hero number or headline per block; `text-lg font-semibold` titles; generous padding (`px-6`, `min-h-16` headings) | Same weight everywhere; crowded toolbars |
| Chart honesty | `InsightsGraph`, funnel, bar tables; flat 2D; colors from domain config. New charts: start with [Nivo](https://nivo.rocks/) | 3D charts, pie charts when a bar is clearer, chartjunk |
| Active titles | Conclusion-first `title` in `BlockHeadingStandard`; `subtitle` for context | Passive labels ("Visitors") when a conclusion is known |
| Progressive disclosure | `HelpTooltip`, `Popover` / `PopoverFilter`, collapsible blocks, Reporting wizard steps | 20 columns on first paint; settings clutter in main views |
| Quality over quantity | Extend existing blocks/hooks; one control in `controls` slot | Duplicate filter UIs; new settings page for one flag |
| i18n | `__()` / `_e()` from `@wordpress/i18n` | Hardcoded English strings |
| Consistent data display | Import from `src/utils/formatting.ts`; extend there if missing | Inline `toLocaleString`, duplicate date/number logic |

### Tailwind token reference

- **Semantic text:** `text-text-black`, `text-text-gray`, `text-text-gray-light`
- **Surfaces:** `bg-white`, `bg-gray-50`, `bg-gray-100`, `border-gray-200`
- **Gray scale:** `gray-50` through `gray-900` in `tailwind.config.mjs`
- **Brand accent (sparingly):** `text-primary`, `bg-primary`, `text-blue` — not for decoration
- **Charts:** `src/components/Statistics/insightsConfig.js` and domain-specific color maps; multi-color OK when series need distinction

### Formatting (single source of truth)

Before displaying numbers, dates, percentages, currency, or durations:

1. **Grep/read** [`src/utils/formatting.ts`](../src/utils/formatting.ts).
2. Reuse existing exports.
3. Add new helpers only in that file if nothing fits.

Common exports: `formatNumber`, `formatPercentage`, `formatCurrency`, `formatCurrencyCompact`, `formatDate`, `formatDateShort`, `formatDateAndTime`, `formatDuration`, `getChangePercentage`, `getRelativeTime`, `formatAxisLabel`, `formatTooltipLabel`, `createValueFormatter`, `truncateMiddle`, `getDisplayDates`, `availableRanges`.

### UI checklist

Use before merging UI changes:

- [ ] Does every visible element help a decision?
- [ ] Is the most important number/message visually dominant?
- [ ] Are titles active/conclusion-first where data supports it?
- [ ] Is secondary detail behind tooltip, popover, or secondary row?
- [ ] Are colors from the design system (not ad hoc)?
- [ ] No new vanity metrics or chart types without product justification?
- [ ] Are all displayed values formatted via `formatting.ts`?

### Reference components

- Block shell: `src/components/Blocks/Block.tsx`, `BlockHeadingStandard.tsx`
- Stat row: `src/components/Common/ExplanationAndStatsItem.js`
- Help / disclosure: `src/components/Common/HelpTooltip.tsx`, `PopoverFilter.js`
- Insights (query + chart): `src/components/Statistics/InsightsBlock.js`
