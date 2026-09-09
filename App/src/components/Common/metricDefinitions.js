import { __ } from '@wordpress/i18n';

/**
 * Single source of truth for metric label, definition, optional "why it matters",
 * and optional privacy note shown in metric-explainer tooltips across the dashboard.
 *
 * Rules: one dominant answer per field; front-load the key word; never restate
 * the label; plain language; privacy notes only where they build trust.
 *
 * Set `url` on an entry to surface a "Learn more" link at the foot of the tooltip.
 *
 * @type {Object.<string, {label: string, definition: string, whyItMatters?: string, privacyNote?: string, url?: string}>}
 */
export const METRIC_DEFINITIONS = {
	visitors: {
		label: __( 'Visitors', 'burst-mainwp' ),
		definition: __( 'Unique people who visited your site', 'burst-mainwp' )
	},
	sessions: {
		label: __( 'Sessions', 'burst-mainwp' ),
		definition: __( 'A single continuous visit: one or more pageviews by the same visitor, ending after 30 minutes of inactivity.', 'burst-mainwp' )
	},
	pageviews: {
		label: __( 'Pageviews', 'burst-mainwp' ),
		definition: __( 'Total number of pages loaded, including repeated views of the same page by the same visitor.', 'burst-mainwp' )
	},
	bounces: {
		label: __( 'Bounces', 'burst-mainwp' ),
		definition: __( 'Sessions where only one page was viewed or user left the page within 5 seconds.', 'burst-mainwp' )
	},
	bounce_rate: {
		label: __( 'Bounce Rate', 'burst-mainwp' ),
		definition: __( 'Percentage of sessions where only one page was viewed or user left the page within 5 seconds.', 'burst-mainwp' ),
		whyItMatters: __( 'High bounce rates on landing pages often signal slow load times or mismatched search intent.', 'burst-mainwp' )
	},
	conversions: {
		label: __( 'Conversions', 'burst-mainwp' ),
		definition: __( 'Number of times a visitor completed a goal you configured, such as a form submission or button click.', 'burst-mainwp' )
	},
	conversion_rate: {
		label: __( 'Conversion rate', 'burst-mainwp' ),
		definition: __( 'Percentage of visitors who completed a goal during the selected period.', 'burst-mainwp' ),
		whyItMatters: __( 'Even a small improvement here can significantly increase the return from your existing traffic.', 'burst-mainwp' )
	},
	time_on_page: {
		label: __( 'Time on page', 'burst-mainwp' ),
		definition: __( 'Average time visitors spent on a page, measured from page load to the next navigation event.', 'burst-mainwp' ),
		whyItMatters: __( 'Short times on content-heavy pages may indicate visitors are not finding what they expected.', 'burst-mainwp' )
	},

	// Device categories.
	desktop: {
		label: __( 'Desktop', 'burst-mainwp' ),
		definition: __( 'Visitors using a desktop or laptop computer, identified by screen width and user-agent.', 'burst-mainwp' )
	},
	tablet: {
		label: __( 'Tablet', 'burst-mainwp' ),
		definition: __( 'Visitors on tablet-sized screens such as an iPad, identified by screen width and user-agent.', 'burst-mainwp' )
	},
	mobile: {
		label: __( 'Mobile', 'burst-mainwp' ),
		definition: __( 'Visitors on phones and small-screen devices, identified by screen width and user-agent.', 'burst-mainwp' )
	},
	other: {
		label: __( 'Other', 'burst-mainwp' ),
		definition: __( 'Devices that could not be classified as desktop, tablet, or mobile. Including smart TVs, game consoles, and bots that passed bot filtering.', 'burst-mainwp' )
	},

	// Ecommerce charts.
	sales_forecast_chart: {
		label: __( 'Revenue over time', 'burst-mainwp' ),
		definition: __( 'Your store\'s total revenue per period: tracked orders plus subscription renewals. With "Next 12 months" enabled, the chart shows the last 12 complete months and the dashed line projects the next 12: each month starts from the same month last year, scaled by your store\'s year-over-year growth, and the current month blends what is already earned with its own pace.', 'burst-mainwp' ),
		whyItMatters: __( 'Seeing measured revenue and its projection in one line helps you spot seasonality and plan ahead.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},
	subscription_forecast_chart: {
		label: __( 'Subscription renewals over time', 'burst-mainwp' ),
		definition: __( 'Subscription renewal payments per period. With "Next 12 months" enabled, the chart shows the last 12 complete months and the dashed forecast projects the next 12 from the same month last year, scaled by the net year-over-year renewal growth — which already accounts for churn and new subscribers.', 'burst-mainwp' ),
		whyItMatters: __( 'Renewals are your recurring baseline: projecting them shows the revenue you can count on before any new sales.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},

	// Growth block.
	growth_forecast_this_year: {
		label: __( 'Forecasted revenue this year', 'burst-mainwp' ),
		definition: __( 'Projected total revenue for the current calendar year: the completed months as measured, plus the forecast for the remaining months — the current month extrapolated from its own pace. The change compares it with last year\'s total.', 'burst-mainwp' ),
		whyItMatters: __( 'The projected year total against last year is the quickest honest read on whether your store is actually growing.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},
	growth_forecast_this_month: {
		label: __( 'Forecasted revenue this month', 'burst-mainwp' ),
		definition: __( 'Projected total revenue for the current month: what is already earned — read from your store\'s own order records, including subscription renewals, where available — extrapolated over the full month at its own pace. The change compares it with the same month last year, so seasonality does not read as growth or decline.', 'burst-mainwp' ),
		whyItMatters: __( 'Comparing this month to the same month last year shows whether you are ahead of your own seasonal pattern.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},
	growth_forecast_next_year: {
		label: __( 'Forecasted revenue next year', 'burst-mainwp' ),
		definition: __( 'Projected revenue for the next calendar year, using the same model as the revenue chart\'s forecast. The change compares it with this year\'s projected total.', 'burst-mainwp' ),
		whyItMatters: __( 'A yearly projection helps you budget and set targets before the year starts.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},
	growth_forecast_next_month: {
		label: __( 'Forecasted revenue next month', 'burst-mainwp' ),
		definition: __( 'Projected revenue for next month, using the same model as the revenue chart\'s forecast. The change compares it with the same month last year, so it shows growth on top of your seasonal pattern.', 'burst-mainwp' ),
		whyItMatters: __( 'Knowing what next month is likely to bring helps you plan stock, campaigns and cash flow.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/how-sales-forecasts-are-calculated/'
	},

	// Engagement metrics.
	outgoing_links: {
		label: __( 'Outgoing links', 'burst-mainwp' ),
		definition: __( 'Clicks on links that lead visitors away from your site, tracked by attaching a click listener to external anchor elements.', 'burst-mainwp' ),
		whyItMatters: __( 'Frequently clicked outbound links show where your visitors go next. Useful for partnership and monetization decisions.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/external-link-tracking-see-where-your-visitors-go-next/'
	},
	forms: {
		label: __( 'Forms', 'burst-mainwp' ),
		definition: __( 'Tracks form views, starts, and submissions for any form on your site, including Contact Form 7, Gravity Forms, and WPForms.', 'burst-mainwp' ),
		whyItMatters: __( 'Comparing submissions to views reveals your form conversion rate and highlights drop-off points.', 'burst-mainwp' )
	},
	search_terms: {
		label: __( 'Website searches', 'burst-mainwp' ),
		definition: __( 'Words and phrases visitors typed into your site\'s own search bar, captured from the search query parameter in the URL.', 'burst-mainwp' ),
		whyItMatters: __( 'Zero-result searches reveal content gaps. Topics your visitors want but cannot find on your site.', 'burst-mainwp' ),
		url: 'https://burst-statistics.com/guides/search-insights-see-what-visitors-are-looking-for-on-your-website/'
	},
	not_found_pages: {
		label: __( '404 Pages', 'burst-mainwp' ),
		definition: __( 'Pages that returned a 404 (Not Found) status code, showing which broken URLs visitors are hitting.', 'burst-mainwp' ),
		whyItMatters: __( 'Frequently hit 404 URLs point to broken inbound links, missing redirects, or old pages that need fixing.', 'burst-mainwp' )
	},
	reading_engagement: {
		label: __( 'Reading engagement', 'burst-mainwp' ),
		definition: __( 'A score (0–100) calculated by comparing average time on page against estimated reading time based on page word count (200 words per minute).', 'burst-mainwp' ),
		whyItMatters: __( 'Reading time alone can be misleading: spending 2 minutes on a 400-word page shows high engagement, but the same 2 minutes on a 2000-word article shows low engagement.', 'burst-mainwp' )
	},

	// Live count.
	live_visitors: {
		label: __( 'Live', 'burst-mainwp' ),
		definition: __( 'Visitors who loaded a page on your site in the last 5 minutes, updated automatically.', 'burst-mainwp' )
	}
};
