import { __, sprintf } from '@wordpress/i18n';

/**
 * Filter categories configuration.
 */
export const FILTER_CATEGORIES = {
	content: {
		label: __( 'Context', 'burst-mainwp' ),
		icon: 'content',
		order: 1
	},
	sources: {
		label: __( 'Sources', 'burst-mainwp' ),
		icon: 'source',
		order: 2
	},
	behavior: {
		label: __( 'Behavior', 'burst-mainwp' ),
		icon: 'behavior',
		order: 3
	},
	location: {
		label: __( 'Location', 'burst-mainwp' ),
		icon: 'location',
		order: 4
	}
} as const;

export type FilterCategory = keyof typeof FILTER_CATEGORIES;

/**
 * Filter configuration interface.
 */
export interface FilterConfig {
	label: string;
	icon: string;
	type: 'string' | 'boolean' | 'int';
	options?: string;
	pro: boolean;
	category: FilterCategory;
	reloadOnSearch?: boolean;
	coming_soon?: boolean;
	exclusion_allowed?: boolean;
	multi_select?: boolean;

	/** When set, shows a time-limited "New" badge. Remove coming_soon and add this when launching a feature. */
	new_badge?: { version: string; days: number; tooltip?: string };

	/** Singular/plural noun used in the collapsed multi-value chip count badge, e.g. "page" / "pages". */
	countNoun?: { singular: string; plural: string };
}

/**
 * Filter configuration with labels, icons, and categories.
 */
export const FILTER_CONFIG: Record<string, FilterConfig> = {

	// Free Filters.
	page_url: {
		label: __( 'Page', 'burst-mainwp' ),
		icon: 'page',
		type: 'string',
		options: 'pages',
		pro: false,
		category: 'content',
		reloadOnSearch: true,
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'page', 'burst-mainwp' ), plural: __( 'pages', 'burst-mainwp' ) }
	},
	referrer: {
		label: __( 'Referrer', 'burst-mainwp' ),
		icon: 'referrer',
		type: 'string',
		options: 'referrers',
		pro: false,
		category: 'sources',
		reloadOnSearch: true,
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'referrer', 'burst-mainwp' ), plural: __( 'referrers', 'burst-mainwp' ) }
	},
	goal_id: {
		label: __( 'Goal', 'burst-mainwp' ),
		icon: 'goals',
		type: 'string',
		options: 'goals',
		pro: false,
		category: 'content',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'goal', 'burst-mainwp' ), plural: __( 'goals', 'burst-mainwp' ) }
	},
	bounces: {
		label: __( 'Bounce', 'burst-mainwp' ),
		icon: 'bounce',
		type: 'boolean',
		pro: false,
		category: 'behavior',
		exclusion_allowed: false
	},
	status: {
		label: __( 'Status', 'burst-mainwp' ),
		icon: 'page',
		type: 'boolean',
		pro: false,
		category: 'content',
		exclusion_allowed: true
	},
	device_id: {
		label: __( 'Device', 'burst-mainwp' ),
		icon: 'desktop',
		type: 'string',
		options: 'devices',
		pro: false,
		category: 'content',
		exclusion_allowed: false,
		multi_select: true,
		countNoun: { singular: __( 'device', 'burst-mainwp' ), plural: __( 'devices', 'burst-mainwp' ) }
	},

	// Pro Filters.
	host: {
		label: __( 'Domain', 'burst-mainwp' ),
		icon: 'browser',
		type: 'string',
		options: 'hosts',
		pro: true,
		category: 'sources',
		multi_select: true,
		countNoun: { singular: __( 'domain', 'burst-mainwp' ), plural: __( 'domains', 'burst-mainwp' ) }
	},
	new_visitor: {
		label: __( 'Visitor type', 'burst-mainwp' ),
		icon: 'user',
		type: 'boolean',
		pro: true,
		category: 'behavior',
		exclusion_allowed: false
	},
	bounce_rate: {
		label: __( 'Bounce Rate', 'burst-mainwp' ),
		icon: 'bounce',
		type: 'int',
		pro: true,
		category: 'behavior',
		coming_soon: true
	},
	entry_exit_pages: {
		label: __( 'Page type', 'burst-mainwp' ),
		icon: 'bounce',
		type: 'boolean',
		pro: true,
		category: 'behavior',
		exclusion_allowed: false
	},
	conversion_rate: {
		label: __( 'Conversion Rate', 'burst-mainwp' ),
		icon: 'conversion',
		type: 'int',
		pro: true,
		category: 'behavior',
		coming_soon: true
	},
	parameter: {
		label: __( 'URL parameter', 'burst-mainwp' ),
		icon: 'parameters',
		type: 'string',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	utm_campaign: {
		label: __( 'UTM campaign', 'burst-mainwp' ),
		icon: 'campaign',
		type: 'string',
		options: 'campaigns',
		pro: true,
		category: 'sources',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'campaign', 'burst-mainwp' ), plural: __( 'campaigns', 'burst-mainwp' ) }
	},
	utm_source: {
		label: __( 'UTM source', 'burst-mainwp' ),
		icon: 'source',
		type: 'string',
		options: 'sources',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	utm_medium: {
		label: __( 'UTM medium', 'burst-mainwp' ),
		icon: 'medium',
		type: 'string',
		options: 'mediums',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	utm_term: {
		label: __( 'UTM term', 'burst-mainwp' ),
		icon: 'term',
		type: 'string',
		options: 'terms',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	utm_content: {
		label: __( 'UTM content', 'burst-mainwp' ),
		icon: 'content',
		type: 'string',
		options: 'contents',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	source: {
		label: __( 'Source', 'burst-mainwp' ),
		icon: 'source',
		type: 'string',
		options: 'traffic_sources',
		pro: true,
		category: 'sources',
		exclusion_allowed: true
	},
	source_category: {
		label: __( 'Source Category', 'burst-mainwp' ),
		icon: 'source',
		type: 'string',
		options: 'source_categories',
		pro: true,
		category: 'sources',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'source', 'burst-mainwp' ), plural: __( 'sources', 'burst-mainwp' ) }
	},
	medium: {
		label: __( 'Medium', 'burst-mainwp' ),
		icon: 'medium',
		type: 'string',
		pro: true,
		category: 'sources',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'content value', 'burst-mainwp' ), plural: __( 'content values', 'burst-mainwp' ) }
	},
	country_code: {
		label: __( 'Country', 'burst-mainwp' ),
		icon: 'world',
		type: 'string',
		options: 'countries',
		pro: true,
		category: 'location',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'country', 'burst-mainwp' ), plural: __( 'countries', 'burst-mainwp' ) }
	},
	state: {
		label: __( 'State', 'burst-mainwp' ),
		icon: 'map-pinned',
		type: 'string',
		options: 'states',
		pro: true,
		category: 'location',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'state', 'burst-mainwp' ), plural: __( 'states', 'burst-mainwp' ) }
	},
	city: {
		label: __( 'City', 'burst-mainwp' ),
		icon: 'city',
		type: 'string',
		options: 'cities',
		pro: true,
		category: 'location',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'city', 'burst-mainwp' ), plural: __( 'cities', 'burst-mainwp' ) }
	},
	continent_code: {
		label: __( 'Continent', 'burst-mainwp' ),
		icon: 'continent',
		type: 'string',
		options: 'continents',
		pro: true,
		category: 'location',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'continent', 'burst-mainwp' ), plural: __( 'continents', 'burst-mainwp' ) }
	},
	time_per_session: {
		label: __( 'Time per session', 'burst-mainwp' ),
		icon: 'time',
		type: 'int',
		pro: true,
		category: 'behavior',
		new_badge: { version: '3.2.3', days: 30, tooltip: __( 'New in 3.2.3 – filter visitors by how long they spent on your site.', 'burst-mainwp' ) }
	},
	platform_id: {
		label: __( 'Operating system', 'burst-mainwp' ),
		icon: 'operating-system',
		type: 'string',
		options: 'platforms',
		pro: true,
		category: 'content',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'operating system', 'burst-mainwp' ), plural: __( 'operating systems', 'burst-mainwp' ) }
	},
	browser_id: {
		label: __( 'Browser', 'burst-mainwp' ),
		icon: 'browser',
		type: 'string',
		options: 'browsers',
		pro: true,
		category: 'content',
		exclusion_allowed: true,
		multi_select: true,
		countNoun: { singular: __( 'browser', 'burst-mainwp' ), plural: __( 'browsers', 'burst-mainwp' ) }
	}
};

// Get all filter keys from config.
export const FILTER_KEYS = Object.keys( FILTER_CONFIG ) as FilterKey[];

// Type for filter keys.
export type FilterKey = keyof typeof FILTER_CONFIG;

// Trailing parameter key to prevent URL parsing issues with hash fragments.
export const TRAILING_PARAM_KEY = '_';

/**
 * Filter search params type - each filter key maps to an optional string.
 * Exclusion is encoded as a '!' prefix on the value (e.g. '!google.com').
 */
export type FilterSearchParams = {
	[K in FilterKey]?: string;
} & {
	[TRAILING_PARAM_KEY]?: string;
};

/**
 * Validates and parses search params for filters.
 * Used by TanStack Router's validateSearch.
 *
 * @param search - The raw search params from the URL.
 * @return Validated filter search params.
 */
export const validateFilterSearch = (
	search: Record<string, unknown>
): FilterSearchParams & { burst_share_token?: string } => {
	const filters: FilterSearchParams & { burst_share_token?: string } = {};

	FILTER_KEYS.forEach( ( key ) => {
		const value = search[key];
		if ( 'string' === typeof value && '' !== value ) {
			filters[key] = value;
		}
	});

	// Preserve trailing param for URL parsing safety.
	if ( TRAILING_PARAM_KEY in search ) {
		filters[TRAILING_PARAM_KEY] = '';
	}

	// Preserve the share token across client-side navigations so the backend
	// can identify shared viewers on every page-load and API request.
	if ( 'string' === typeof search.burst_share_token && '' !== search.burst_share_token ) {
		filters.burst_share_token = search.burst_share_token;
	}

	return filters;
};

/**
 * Checks if a filter value indicates exclusion (starts with '!').
 *
 * @param value - The filter value to check.
 *
 * @return True if the value indicates exclusion, false otherwise.
 */
export const isExcluding = ( value: string | undefined ): boolean => {
	return !! value && value.startsWith( '!' );
};

/**
 * Splits a comma-separated filter value into a trimmed, non-empty array of raw values.
 * Assumes any leading exclusion '!' has already been stripped by the caller.
 *
 * @param value - The (already unprefixed) filter value, e.g. '/a,/b'.
 *
 * @return Array of individual raw values.
 */
export const splitFilterValues = ( value: string | undefined ): string[] => {
	if ( ! value ) {
		return [];
	}
	return value.split( ',' ).map( ( v ) => v.trim() ).filter( Boolean );
};

/**
 * Normalizes a filter value to the string form every filter consumer expects.
 *
 * Filter values travel through the URL search params and the saved-filter
 * store as strings, and the display and setup components call string methods
 * on them (split, startsWith, trim). Callers that pass a numeric id straight
 * from an API response (the devices block sets device_id from the lookup id)
 * would otherwise store a number: TanStack Router round-trips it as a number,
 * so the chip builder and the device setup view throw on it and the filter
 * never shows as active.
 *
 * @param value - The raw filter value, e.g. a string, a numeric id, or empty.
 *
 * @return The string value, or '' for null/undefined.
 */
export const normalizeFilterValue = ( value: unknown ): string => {
	if ( null === value || value === undefined ) {
		return '';
	}
	return String( value );
};

/**
 * The four explicit operators a filter chip can render.
 */
export type FilterOperator = 'is' | 'is-not' | 'is-any-of' | 'is-not-any-of';

/**
 * Human-readable, translatable labels for each filter operator.
 */
export const FILTER_OPERATOR_LABELS: Record<FilterOperator, string> = {
	is: __( 'is', 'burst-mainwp' ),
	'is-not': __( 'is not', 'burst-mainwp' ),
	'is-any-of': __( 'is any of', 'burst-mainwp' ),
	'is-not-any-of': __( 'is not one of', 'burst-mainwp' )
};

/**
 * Derives the explicit operator for a filter based on whether it excludes and
 * whether it currently has more than one selected value.
 *
 * @param options            - Operator inputs.
 * @param options.isExcluded  - Whether the filter is in exclude mode.
 * @param options.isMultiValue - Whether the filter currently has 2+ values.
 *
 * @return The matching filter operator.
 */
export const getFilterOperator = ({ isExcluded, isMultiValue }: { isExcluded: boolean; isMultiValue: boolean }): FilterOperator => {
	if ( isExcluded ) {
		return isMultiValue ? 'is-not-any-of' : 'is-not';
	}
	return isMultiValue ? 'is-any-of' : 'is';
};

/**
 * Builds the translatable "( N noun )" count label shown on a collapsed
 * multi-value filter chip, e.g. "( 13 pages )". Falls back to a generic
 * "value" / "values" noun when the filter has no configured countNoun.
 *
 * @param config - The filter's configuration (for its countNoun), or null.
 * @param count  - The number of selected values.
 *
 * @return The formatted count label.
 */
// fallow-ignore-next-line complexity
export const buildCountLabel = ( config: FilterConfig | null | undefined, count: number ): string => {
	const singular = config?.countNoun?.singular || __( 'value', 'burst-mainwp' );
	const plural = config?.countNoun?.plural || __( 'values', 'burst-mainwp' );
	const noun = 1 === count ? singular : plural;

	return sprintf( '%1$d %2$s', count, noun );
};

/**
 * Initial filter state - all filters empty.
 */
export const INITIAL_FILTERS: FilterSearchParams = FILTER_KEYS.reduce(
	( acc, key ) => {
		acc[key] = '';
		return acc;
	},
	{} as FilterSearchParams
);

// Default favorites for new users.
export const DEFAULT_FAVORITES = [ 'page_url', 'referrer', 'bounces', 'device_id' ];

