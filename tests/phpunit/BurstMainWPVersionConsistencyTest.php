<?php
use PHPUnit\Framework\TestCase;

class BurstMainWPVersionConsistencyTest extends TestCase {

    /**
     * Verifies that the "Tested up to" version in readme.txt is greater than or
     * equal to the latest WordPress release when ignoring patch versions. Only a
     * major.minor match is required, so "Tested up to: 7.1" covers all 7.1.x
     * releases. A failure only appears when the major.minor of the latest WP
     * version exceeds the tested up to value.
     */
    public function test_tested_up_to_version() {
        $plugin_dir  = dirname( __FILE__, 3 );
        $readme_path = $plugin_dir . '/readme.txt';

        // Get "Tested up to" from readme.txt
        $tested_up_to = $this->get_tested_up_to( $readme_path );
        $this->assertNotNull( $tested_up_to, 'Could not find "Tested up to" in readme.txt' );

        // Fetch latest WordPress version from the official API
        $response = file_get_contents( 'https://api.wordpress.org/core/version-check/1.7/' );
        $this->assertNotFalse( $response, 'Could not fetch WordPress version from API' );

        $data = json_decode( $response, true );
        $this->assertNotNull( $data, 'Could not parse WordPress version API response' );

        $latest_wp_version = $data['offers'][0]['version'] ?? null;
        $this->assertNotNull( $latest_wp_version, 'Could not find version in WordPress API response' );

        // Strip patch version from both values, leaving only major.minor (e.g. 6.9.1 -> 6.9)
        $tested_up_to_normalized = $this->normalize_to_minor( $tested_up_to );
        $latest_wp_normalized    = $this->normalize_to_minor( $latest_wp_version );

        // Tested up to major.minor must be >= latest WP major.minor
        $this->assertGreaterThanOrEqual(
            0,
            version_compare( $tested_up_to_normalized, $latest_wp_normalized ),
            "\"Tested up to\" ($tested_up_to) must be greater than or equal to the latest WordPress major.minor version ($latest_wp_normalized)."
        );
    }

    private function normalize_to_minor( string $version ): string {
        $parts = explode( '.', $version );

        // Return only major.minor (first two segments)
        return implode( '.', array_slice( $parts, 0, 2 ) );
    }

    private function get_tested_up_to( string $file_path ): ?string {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $content = file_get_contents( $file_path );
        if ( preg_match( '/^Tested up to:\s*(\d+\.\d+(?:\.\d+)?)/mi', $content, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    public function test_version_consistency() {
        $plugin_dir = dirname( __FILE__, 3 );

        // Get version from readme.txt
        $readme_version = $this->get_readme_version( $plugin_dir . '/readme.txt' );

        // Get version from the plugin header in burst-mainwp.php
        $plugin_version = $this->get_plugin_version( $plugin_dir . '/burst-mainwp.php' );

        // Get version from the BURST_MAINWP_VERSION constant in burst-mainwp.php
        $constant_version = $this->get_constant_version( $plugin_dir . '/burst-mainwp.php' );

        // Assert all versions are found
        $this->assertNotNull( $readme_version, 'Could not find version in readme.txt' );
        $this->assertNotNull( $plugin_version, 'Could not find version in burst-mainwp.php header' );
        $this->assertNotNull( $constant_version, 'Could not find BURST_MAINWP_VERSION constant in burst-mainwp.php' );

        // Assert all versions match
        $this->assertEquals(
            $readme_version,
            $plugin_version,
            "Version mismatch: readme.txt ($readme_version) vs burst-mainwp.php header ($plugin_version)"
        );

        $this->assertEquals(
            $readme_version,
            $constant_version,
            "Version mismatch: readme.txt ($readme_version) vs BURST_MAINWP_VERSION ($constant_version)"
        );

        $this->assertEquals(
            $plugin_version,
            $constant_version,
            "Version mismatch: burst-mainwp.php header ($plugin_version) vs BURST_MAINWP_VERSION ($constant_version)"
        );
    }

    public function test_version_format() {
        $plugin_dir = dirname( __FILE__, 3 );

        $readme_version   = $this->get_readme_version( $plugin_dir . '/readme.txt' );
        $plugin_version   = $this->get_plugin_version( $plugin_dir . '/burst-mainwp.php' );
        $constant_version = $this->get_constant_version( $plugin_dir . '/burst-mainwp.php' );

        // Test that versions match either x.x.x or x.x.x.x format
        $valid_format = '/^\d+\.\d+\.\d+(?:\.\d+)?$/';

        $this->assertMatchesRegularExpression( $valid_format, $readme_version, 'readme.txt version has invalid format' );
        $this->assertMatchesRegularExpression( $valid_format, $plugin_version, 'burst-mainwp.php header version has invalid format' );
        $this->assertMatchesRegularExpression( $valid_format, $constant_version, 'BURST_MAINWP_VERSION has invalid format' );
    }

    public function test_changelog_structure() {
        $plugin_dir  = dirname( __FILE__, 3 );
        $readme_path = $plugin_dir . '/readme.txt';

        $this->assertFileExists( $readme_path, 'readme.txt file not found' );

        $readme_version = $this->get_readme_version( $readme_path );
        $this->assertNotNull( $readme_version, 'Could not find stable tag version in readme.txt' );

        $content = file_get_contents( $readme_path );

        // Find the changelog section for the current version
        $changelog_entry = $this->get_changelog_entry( $content, $readme_version );

        $this->assertNotNull(
            $changelog_entry,
            "Changelog entry not found for version $readme_version"
        );

        // Check if changelog has a date
        $has_date = $this->changelog_has_date( $changelog_entry );
        $this->assertTrue(
            $has_date,
            "Changelog for version $readme_version is missing a date. Found content:\n" . $changelog_entry
        );

        // Check if changelog has at least one change line
        $change_count = $this->count_changelog_changes( $changelog_entry );
        $this->assertGreaterThan(
            0,
            $change_count,
            "Changelog for version $readme_version must have at least one change entry (Fix, Improvement, New, or Security)"
        );
    }

    private function get_readme_version( string $file_path ): ?string {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $content = file_get_contents( $file_path );
        if ( preg_match( '/^Stable tag:\s*(\d+\.\d+\.\d+(?:\.\d+)?)/m', $content, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    private function get_plugin_version( string $file_path ): ?string {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $content = file_get_contents( $file_path );
        if ( preg_match( '/\*\s*Version:\s*(\d+\.\d+\.\d+(?:\.\d+)?)/i', $content, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    private function get_constant_version( string $file_path ): ?string {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $content = file_get_contents( $file_path );
        if ( preg_match( '/define\s*\(\s*[\'"]BURST_MAINWP_VERSION[\'"]\s*,\s*[\'"](\d+\.\d+\.\d+(?:\.\d+)?)[\'"]/', $content, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    private function get_changelog_entry( string $content, string $version ): ?string {
        // Escape dots in version for regex
        $version_escaped = preg_quote( $version, '/' );

        // Match the version header and everything until the next version or end of changelog
        // Updated to handle both = format and potential whitespace variations
        $pattern = '/^=+\s*' . $version_escaped . '\s*=+\s*\n(.*?)(?=\n=+\s*\d+\.\d+\.\d+(?:\.\d+)?\s*=+|\z)/ms';

        if ( preg_match( $pattern, $content, $matches ) ) {
            return trim( $matches[1] );
        }

        return null;
    }

    private function changelog_has_date( string $changelog_entry ): bool {
        // Check for date patterns - more flexible patterns
        $date_patterns = [
            // Month name with day number (with or without asterisk, case insensitive)
            '/[\*\s]*(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}(st|nd|rd|th)?/i',
            // ISO date format
            '/\d{4}-\d{2}-\d{2}/',
            // Common date formats
            '/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/',
        ];

        foreach ( $date_patterns as $pattern ) {
            if ( preg_match( $pattern, $changelog_entry ) ) {
                return true;
            }
        }

        return false;
    }

    private function count_changelog_changes( string $changelog_entry ): int {
        // Count lines that start with * followed by Fix, Improvement, New, or Security
        $pattern = '/^\*\s*(Fix|Improvement|New|Security):/m';

        preg_match_all( $pattern, $changelog_entry, $matches );

        return count( $matches[0] );
    }
}
