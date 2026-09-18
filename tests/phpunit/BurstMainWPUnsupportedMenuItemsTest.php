<?php
use PHPUnit\Framework\TestCase;

/**
 * The child site's menu is handed to the bundled React app as-is, minus the
 * sub-pages that cannot work inside the MainWP dashboard. A Burst 3.7.1+
 * child adds Settings > Data (import/export); without this filter the app
 * renders "Unknown field type: import_data" on that page.
 */
class BurstMainWPUnsupportedMenuItemsTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', dirname( __FILE__, 3 ) . '/' );
        }
        require_once dirname( __FILE__, 3 ) . '/class/class-individual.php';
    }

    private function child_menu(): array {
        return [
            [
                'id'         => 'reporting',
                'menu_items' => [
                    [ 'id' => 'reports' ],
                    [ 'id' => 'customization' ],
                ],
            ],
            [
                'id'         => 'settings',
                'menu_items' => [
                    [ 'id' => 'general' ],
                    [ 'id' => 'data' ],
                    [ 'id' => 'goals' ],
                ],
            ],
            [ 'id' => 'statistics' ],
        ];
    }

    public function test_removes_customization_and_data_sub_pages(): void {
        $menu = \BurstMainWP\Individual::remove_unsupported_menu_items( $this->child_menu() );

        $this->assertSame( [ [ 'id' => 'reports' ] ], $menu[0]['menu_items'] );
        $this->assertSame( [ [ 'id' => 'general' ], [ 'id' => 'goals' ] ], $menu[1]['menu_items'], 'Remaining items are re-indexed.' );
    }

    public function test_leaves_other_menus_untouched(): void {
        $menu = \BurstMainWP\Individual::remove_unsupported_menu_items( $this->child_menu() );

        $this->assertSame( [ 'id' => 'statistics' ], $menu[2] );
        $this->assertCount( 3, $menu );
    }
}
