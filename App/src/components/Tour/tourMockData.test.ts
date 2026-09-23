import assert from 'node:assert/strict';
import test from 'node:test';

interface MockWindow {
	location: {
		search: string;
		href: string;
		hash: string;
		hostname: string;
	};
	__burst_is_tour_active?: () => boolean;
}

const originalWindow = global.window;
const mockWindow: MockWindow = {
	location: {
		search: '',
		href: 'http://localhost/wp-admin/admin.php?page=burst',
		hash: '',
		hostname: 'localhost'
	}
};

const testGlobal: Record<string, unknown> = globalThis;

// Loaded in `before` so the window mock is installed before the module is
// evaluated, independent of CommonJS/ESM import hoisting.
let getFrontendTourMockData: ( path: string ) => unknown;

test.before( async() => {
	testGlobal.window = mockWindow;
	({ getFrontendTourMockData } = await import( './tourMockData' ) );
});

test.after( () => {
	testGlobal.window = originalWindow;
});

test.beforeEach( () => {
	mockWindow.location.search = '';
	delete mockWindow.__burst_is_tour_active;
});

test( 'getFrontendTourMockData returns null when tour is not active', () => {
	const mock = getFrontendTourMockData( 'data/overview' );
	assert.strictEqual( mock, null );
});

test( 'getFrontendTourMockData returns mock data when ?tour=dashboard is in URL', () => {
	mockWindow.location.search = '?page=burst&tour=dashboard';

	const insightsMock = getFrontendTourMockData( 'data/insights' ) as { request_success: boolean; data: { datasets: Array<{ label: string }> } };
	assert.ok( null !== insightsMock, 'Insights mock should return data' );
	assert.strictEqual( insightsMock.request_success, true );
	assert.ok( Array.isArray( insightsMock.data.datasets ), 'Insights should include datasets' );
});

test( 'getFrontendTourMockData follows the tour store when it reports mock data active (Sales upsell, demo mode)', () => {

	// URL has NO tour parameter; the store (exposed as __burst_is_tour_active) says mock data is on.
	mockWindow.location.search = '?page=burst';
	mockWindow.__burst_is_tour_active = () => true;

	const datatableMock = getFrontendTourMockData( 'data/datatable/upsell_pages' ) as {
		request_success: boolean;
		data: { columns: Array<{ id: string }>; data: Array<Record<string, unknown>>; total_rows: number };
	};

	assert.ok( null !== datatableMock, 'Datatable mock should return data when the store reports mock data active' );
	assert.strictEqual( datatableMock.request_success, true );
	assert.ok( Array.isArray( datatableMock.data.columns ), 'Columns should be an array' );
	assert.ok( Array.isArray( datatableMock.data.data ), 'Data should be an array' );
	assert.ok( 0 < datatableMock.data.data.length, 'Data rows should not be empty' );

	const firstRow = datatableMock.data.data[0];
	assert.ok( firstRow.page_url !== undefined, 'Row should include page_url' );
	assert.ok( firstRow.pageviews !== undefined, 'Row should include pageviews' );
	assert.ok( firstRow.visitors !== undefined, 'Row should include visitors' );
});

test( 'the tour store verdict wins over the URL when it reports inactive', () => {
	mockWindow.location.search = '?page=burst&tour=dashboard';
	mockWindow.__burst_is_tour_active = () => false;

	assert.strictEqual( getFrontendTourMockData( 'data/insights' ), null );
});

test( 'getFrontendTourMockData provides mock ecommerce data for sales upsell', () => {
	mockWindow.__burst_is_tour_active = () => true;

	const salesMock = getFrontendTourMockData( 'data/ecommerce/sales' ) as { request_success: boolean; data: Record<string, unknown> };
	assert.ok( null !== salesMock, 'Sales mock should return data' );
	assert.strictEqual( salesMock.request_success, true );

	const topPerformersMock = getFrontendTourMockData( 'ecommerce/top-performers' ) as { request_success: boolean; data: unknown };
	assert.ok( null !== topPerformersMock, 'Top performers mock should return data' );
	assert.strictEqual( topPerformersMock.request_success, true );
});

test( 'tour management endpoints are never intercepted by getFrontendTourMockData', () => {
	mockWindow.location.search = '?tour=dashboard';

	assert.strictEqual( getFrontendTourMockData( 'burst/v1/tour/status' ), null );
	assert.strictEqual( getFrontendTourMockData( 'burst/v1/do_action/tour_dismiss' ), null );
	assert.strictEqual( getFrontendTourMockData( 'burst/v1/get_action/tour_steps' ), null );
});
