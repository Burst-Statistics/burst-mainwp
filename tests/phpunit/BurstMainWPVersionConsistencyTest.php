<?php
use PHPUnit\Framework\TestCase;

class BurstMainWPVersionConsistencyTest extends TestCase {

    /**
     * Verifies that the "Tested up to" version in readme.txt matches the major.minor
     * of the latest official WordPress release, or of an available beta/RC. It may not
     * lag behind the stable release, and it may only be higher when that higher version
     * actually exists as a beta or RC — e.g. latest WP 7.0.3 requires "Tested up to: 7.0",
     * but once 7.1-RC1 is out, "Tested up to: 7.1" is also allowed.
     */
    public function test_tested_up_to_version() {
        $plugin_dir  = dirname( __FILE__, 3 );
        $readme_path = $plugin_dir . '/readme.txt';

        // Get "Tested up to" from readme.txt
        $tested_up_to = $this->get_tested_up_to( $readme_path );
        $this->assertNotNull( $tested_up_to, 'Could not find "Tested up to" in readme.txt' );

        // The readme value itself must be major.minor only, without a patch version.
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+$/',
            $tested_up_to,
            "\"Tested up to\" ($tested_up_to) must be a major.minor version without a patch version (e.g. 7.0, not 7.0.3)."
        );

        // Fetch WordPress versions from the official API. The beta channel includes both
        // the latest stable release ('upgrade' offer) and any beta/RC ('development' offer).
        $response = file_get_contents( 'https://api.wordpress.org/core/version-check/1.7/?channel=beta' );
        $this->assertNotFalse( $response, 'Could not fetch WordPress version from API' );

        $data = json_decode( $response, true );
        $this->assertNotNull( $data, 'Could not parse WordPress version API response' );
        $this->assertNotEmpty( $data['offers'] ?? [], 'No offers found in WordPress API response' );

        $latest_stable = null;
        $allowed       = [];
        foreach ( $data['offers'] as $offer ) {
            $version = $offer['version'] ?? '';
            if ( $latest_stable === null && ( $offer['response'] ?? '' ) === 'upgrade' ) {
                // Latest official stable release, e.g. 7.0.4.
                $latest_stable = $this->normalize_to_minor( $version );
                $allowed[]     = $latest_stable;
            } elseif ( preg_match( '/-(beta|RC)/i', $version ) ) {
                // Available beta/RC of an upcoming release, e.g. 7.1-RC4 -> 7.1 is allowed.
                $allowed[] = $this->normalize_to_minor( $version );
            }
        }
        $allowed = array_values( array_unique( $allowed ) );

        $this->assertNotNull( $latest_stable, 'Could not find latest stable version in WordPress API response' );

        // Tested up to may not lag behind the latest stable release, and may only exceed
        // it for a version that exists as a beta/RC (wordpress.org rejects anything higher).
        $this->assertContains(
            $tested_up_to,
            $allowed,
            "\"Tested up to\" ($tested_up_to) must match the latest official WordPress major.minor version, or that of an available beta/RC. Allowed value(s): " . implode( ', ', $allowed ) . '.'
        );
    }

    private function normalize_to_minor( string $version ): string {
        // Strip any pre-release suffix (e.g. 7.1-RC4 -> 7.1), then return major.minor.
        if ( preg_match( '/^(\d+\.\d+)/', $version, $matches ) ) {
            return $matches[1];
        }

        return $version;
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
