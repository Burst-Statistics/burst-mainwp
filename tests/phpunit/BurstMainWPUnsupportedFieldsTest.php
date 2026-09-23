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

    /**
     * Verifies that all settings fields forwarded to the React app have corresponding
     * field components registered in Field.jsx (or 'goals' which is handled directly).
     * If Burst introduces a new field type, this ensures we either support it in the
     * bundled React app or explicitly strip it in remove_unsupported_fields().
     */
    public function test_child_fields_have_registered_react_components(): void {
        $field_jsx_path = dirname( __FILE__, 3 ) . '/App/src/components/Fields/Field.jsx';
        $this->assertFileExists( $field_jsx_path, 'Field.jsx bundled component file not found' );

        $field_jsx = file_get_contents( $field_jsx_path );
        $this->assertNotFalse( $field_jsx );

        preg_match( '/export const fieldComponents = \{(.*?)\};/s', $field_jsx, $matches );
        $this->assertNotEmpty( $matches, 'Could not locate fieldComponents in Field.jsx' );

        preg_match_all( '/\b([a-z0-9_]+)\s*:/', $matches[1], $type_matches );
        $registered_types = array_merge( $type_matches[1], [ 'goals' ] );

        $filtered_fields = \BurstMainWP\Individual::remove_unsupported_fields( $this->child_fields() );
        foreach ( $filtered_fields as $field ) {
            $type = $field['type'] ?? 'text';
            $this->assertContains(
                $type,
                $registered_types,
                sprintf( 'Field "%s" has unregistered type "%s" in Field.jsx', $field['id'], $type )
            );
        }
    }

    /**
     * Verifies that unregistered field types are detected as missing from Field.jsx,
     * which would trigger silent failure and console logging in the React app.
     */
    public function test_detects_unregistered_field_type(): void {
        $field_jsx_path = dirname( __FILE__, 3 ) . '/App/src/components/Fields/Field.jsx';
        $field_jsx      = file_get_contents( $field_jsx_path );
        $this->assertNotFalse( $field_jsx );

        preg_match( '/export const fieldComponents = \{(.*?)\};/s', $field_jsx, $matches );
        $this->assertNotEmpty( $matches );

        preg_match_all( '/\b([a-z0-9_]+)\s*:/', $matches[1], $type_matches );
        $registered_types = array_merge( $type_matches[1], [ 'goals' ] );

        $unknown_field = [ 'id' => 'unknown_new_setting', 'type' => 'unknown_future_field_type' ];
        $this->assertNotContains(
            $unknown_field['type'],
            $registered_types,
            'Unknown field type should not be registered in Field.jsx'
        );
    }
}
