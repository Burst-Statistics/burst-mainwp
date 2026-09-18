<?php
use PHPUnit\Framework\TestCase;

/**
 * The child site's settings fields are handed to the bundled React app as-is,
 * minus the fields that cannot work inside the MainWP dashboard. A Burst 3.7.1
 * child adds the interactive tour's "Start Tour" button, which redirects to the
 * child's own wp-admin; the tour cannot run inside MainWP.
 */
class BurstMainWPUnsupportedFieldsTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', dirname( __FILE__, 3 ) . '/' );
        }
        require_once dirname( __FILE__, 3 ) . '/class/class-individual.php';
    }

    private function child_fields(): array {
        return [
            [ 'id' => 'enable_cookieless_tracking', 'type' => 'checkbox' ],
            [ 'id' => 'interactive_tour', 'type' => 'button', 'action' => 'tour_start' ],
            [ 'id' => 'email_reports_mailinglist', 'type' => 'email_reports' ],
        ];
    }

    public function test_removes_tour_field_and_reindexes(): void {
        $fields = \BurstMainWP\Individual::remove_unsupported_fields( $this->child_fields() );

        $this->assertSame( [ 'enable_cookieless_tracking', 'email_reports_mailinglist' ], array_column( $fields, 'id' ) );
        $this->assertSame( [ 0, 1 ], array_keys( $fields ), 'Remaining fields are re-indexed so the JSON stays a list.' );
    }

    public function test_leaves_fields_without_tour_untouched(): void {
        $fields = [ [ 'id' => 'enable_cookieless_tracking' ], [ 'type' => 'hidden' ] ];

        $this->assertSame( $fields, \BurstMainWP\Individual::remove_unsupported_fields( $fields ) );
    }
}
