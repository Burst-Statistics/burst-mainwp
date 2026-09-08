import { getData } from '@/utils/api';
import {
	formatNumber,
	formatTime,
	getAbsoluteChangePercentage,
	getChangePercentage,
	getPercentage
} from '@/utils/formatting';
import { __ } from '@wordpress/i18n';

const metrics = {
	pageviews: __( 'Pageviews', 'burst-mainwp' ),
	sessions: __( 'Sessions', 'burst-mainwp' ),
	visitors: __( 'Visitors', 'burst-mainwp' ),
	bounce_rate: __( 'Bounce Rate', 'burst-mainwp' ),
	avg_time_on_page: __( 'Avg. time on page', 'burst-mainwp' )
};

const goalMetrics = {
	conversions: __( 'Conversions', 'burst-mainwp' ),
	pageviews: __( 'Pageviews', 'burst-mainwp' ),
	sessions: __( 'Sessions', 'burst-mainwp' ),
	visitors: __( 'Visitors', 'burst-mainwp' ),
	avg_time_on_page: __( 'Avg. time on page', 'burst-mainwp' )
};

const pageMetrics = {
	conversions: __( 'Conversions', 'burst-mainwp' ),
	reading_engagement_score: __( 'Engagement score', 'burst-mainwp' )
};

const divide = ( value, total ) => 0 < total ? value / total : 0;

const getPageviewsPerSession = ( data ) =>
	divide( data.pageviews, data.sessions );

const getTimePerSession = ( data ) =>
	getPageviewsPerSession( data ) * data.avg_time_on_page;

const getNewVisitorsPercentage = ( data ) =>
	divide( data.first_time_visitors, data.visitors ) * 100;

const templates = {
	default: {
		pageviews: ( curr ) => ({
			title: __( 'Pageviews', 'burst-mainwp' ),
			subtitle: __(
				'%s pageviews per session',
				'burst-mainwp'
			).replace( '%s', formatNumber( getPageviewsPerSession( curr ) ) ),
			value: formatNumber( curr.pageviews ),
			exactValue: curr.pageviews,
			communityMetricKey: 'pageviews_per_session',
			communityMetricValue: getPageviewsPerSession( curr )
		}),
		sessions: ( curr ) => ({
			title: __( 'Sessions', 'burst-mainwp' ),
			subtitle: __( '%s per session', 'burst-mainwp' ).replace(
				'%s',
				formatTime( getTimePerSession( curr ) )
			),
			value: formatNumber( curr.sessions ),
			exactValue: curr.sessions,
			communityMetricKey: 'time_per_session',
			communityMetricValue: getTimePerSession( curr )
		}),
		visitors: ( curr ) => ({
			title: __( 'Visitors', 'burst-mainwp' ),
			subtitle: __( '%s are new visitors', 'burst-mainwp' ).replace(
				'%s',
				getPercentage( curr.first_time_visitors, curr.visitors )
			),
			value: formatNumber( curr.visitors ),
			exactValue: curr.visitors,
			communityMetricKey: 'new_visitors_percentage',
			communityMetricValue: getNewVisitorsPercentage( curr )
		}),
		bounce_rate: ( curr ) => ({
			title: __( 'Bounce Rate', 'burst-mainwp' ),
			subtitle: __( '%s visitors bounced', 'burst-mainwp' ).replace(
				'%s',
				curr.bounced_sessions
			),
			value: formatNumber( curr.bounce_rate ) + '%',
			exactValue: curr.bounce_rate,
			communityMetricKey: 'bounce_rate',
			communityMetricValue: curr.bounce_rate
		}),
		avg_time_on_page: ( curr ) => ({
			title: __( 'Avg. time on page', 'burst-mainwp' ),
			subtitle: __( 'Across %s pageviews', 'burst-mainwp' ).replace(
				'%s',
				formatNumber( curr.pageviews )
			),
			value: formatTime( curr.avg_time_on_page ),
			exactValue: null,
			communityMetricKey: 'average_time_on_page',
			communityMetricValue: curr.avg_time_on_page
		}),
		conversions: ( curr ) => ({
			title: __( 'Conversions', 'burst-mainwp' ),
			subtitle: __( '%s of pageviews converted', 'burst-mainwp' ).replace(
				'%s',
				getPercentage( curr.conversions, curr.pageviews )
			),
			value: formatNumber( curr.conversions ),
			exactValue: curr.conversions
		}),
		reading_engagement_score: ( curr ) => ({
			title: __( 'Engagement score', 'burst-mainwp' ),
			subtitle: __( 'Score out of 100', 'burst-mainwp' ),
			value: formatNumber( curr.reading_engagement_score ),
			exactValue: curr.reading_engagement_score
		})
	},
	goalSelected: {
		conversions: ( curr ) => ({
			title: __( 'Conversions', 'burst-mainwp' ),
			subtitle: __(
				'%s of pageviews converted',
				'burst-mainwp'
			).replace( '%s', getPercentage( curr.conversion_rate, 100 ) ),
			value: formatNumber( curr.conversions ),
			exactValue: curr.conversions,
			communityMetricKey: 'conversion_rate',
			communityMetricValue: curr.conversion_rate
		}),
		pageviews: ( curr ) => ({
			title: __( 'Pageviews', 'burst-mainwp' ),
			subtitle: __(
				'%s pageviews per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.pageviews / curr.conversions ) ),
			value: formatNumber( curr.pageviews ),
			exactValue: curr.pageviews
		}),
		sessions: ( curr ) => ({
			title: __( 'Sessions', 'burst-mainwp' ),
			subtitle: __(
				'%s sessions per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.sessions / curr.conversions ) ),
			value: formatNumber( curr.sessions ),
			exactValue: curr.sessions
		}),
		visitors: ( curr ) => ({
			title: __( 'Visitors', 'burst-mainwp' ),
			subtitle: __(
				'%s visitors per conversion',
				'burst-mainwp'
			).replace( '%s', formatNumber( curr.visitors / curr.conversions ) ),
			value: formatNumber( curr.visitors ),
			exactValue: curr.visitors
		})
	}
};

const transformCompareData = ( response, includePageMetrics = false ) => {
	const data = {};
	const curr = response.current;
	const prev = response.previous;

	let templateType = 'default';
	let selectedMetrics = metrics;

	if ( 'goals' === response.view ) {
		templateType = 'goalSelected';
		selectedMetrics = goalMetrics;
	}
	if ( includePageMetrics ) {
		const combinedMetrics = { ...selectedMetrics, ...pageMetrics };
		const pageMetricOrder = [
			'pageviews',
			'sessions',
			'visitors',
			'reading_engagement_score',
			'bounce_rate',
			'conversions'
		];
		selectedMetrics = Object.fromEntries(
			pageMetricOrder
				.filter( ( key ) => key in combinedMetrics )
				.map( ( key ) => [ key, combinedMetrics[key] ])
		);
	}
	const selectedTemplate = templates[templateType];

	Object.entries( selectedMetrics ).forEach( ([ key ]) => {
		const templateFunction =
			selectedTemplate[key] || templates.default[key];

		let change = {};
		if ( 'bounce_rate' === key ) {
			change = getAbsoluteChangePercentage( curr[key], prev[key]);

			change.status =
				'positive' === change.status ? 'negative' : 'positive';
		} else {
			change = getChangePercentage( curr[key], prev[key]);
		}

		data[key] = {
			...templateFunction( curr, prev ),
			change: change.val,
			changeStatus: change.status
		};
	});
	return data;
};

/**
 * Get live visitors
 * @param {Object} args
 * @param {string} args.startDate
 * @param {string} args.endDate
 * @param {string} args.range
 * @param {Object} args.filters
 * @param          args.args
 * @return {Promise<*>}
 */
const getCompareData = async({
	startDate,
	endDate,
	range,
	args,
	includePageMetrics = false
}) => {
	const { data } = await getData( 'compare', startDate, endDate, range, args );
	return transformCompareData( data, includePageMetrics );
};

export default getCompareData;
