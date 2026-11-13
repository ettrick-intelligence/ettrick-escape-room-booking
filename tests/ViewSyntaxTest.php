<?php
/**
 * Tests for view file integrity.
 * Catches orphaned <?php tags, missing pagination components, and raw $sql variables.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'EERB_PLUGIN_DIR' ) ) {
    define( 'EERB_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'EERB_LITE' ) ) {
    define( 'EERB_LITE', true );
}
if ( ! defined( 'EERB_VERSION' ) ) {
    define( 'EERB_VERSION', '1.3.0' );
}

class ViewSyntaxTest extends TestCase {

    private function get_view_files(): array {
        return [
            'admin/views/bookings.php',
            'admin/views/customers.php',
            'admin/views/dashboard.php',
            'admin/views/games.php',
            'admin/views/settings.php',
            'admin/views/upgrade.php',
            'public/views/booking.php',
            'public/views/calendar.php',
            'public/views/manage-booking.php',
        ];
    }

    // ── Orphaned PHP tags ─────────────────────────────────────────────────────

    /**
     * @dataProvider viewFileProvider
     */
    public function test_view_has_no_orphaned_php_open_tags( string $file ) {
        $path   = EERB_PLUGIN_DIR . $file;
        $lines  = file( $path, FILE_IGNORE_NEW_LINES );
        $orphans = [];

        foreach ( $lines as $i => $line ) {
            $trimmed = trim( $line );
            if ( $trimmed === '<?php' || $trimmed === '<?php ' ) {
                for ( $j = $i + 1; $j < count( $lines ); $j++ ) {
                    $next = trim( $lines[ $j ] );
                    if ( $next === '' ) continue;
                    if ( 0 === strpos( $next, '<' ) && 0 !== strpos( $next, '<?php' ) && 0 !== strpos( $next, '<!--' ) ) {
                        $orphans[] = "Line " . ( $i + 1 ) . ": orphaned '<?php' followed by HTML on line " . ( $j + 1 );
                    }
                    break;
                }
            }
        }

        $this->assertEmpty(
            $orphans,
            "View file {$file} has orphaned PHP open tags:\n" . implode( "\n", $orphans )
        );
    }

    public function viewFileProvider(): array {
        return array_map( fn( $f ) => [ $f ], $this->get_view_files() );
    }

    // ── Bookings and customers views have basic query structure ───────────────
    // Note: Lite plugin uses simple LIMIT queries rather than EERB_Pagination.
    // These tests verify the views have correct query structure for Lite.

    public function test_bookings_view_has_query_structure() {
        $source = file_get_contents( EERB_PLUGIN_DIR . 'admin/views/bookings.php' );
        $this->assertStringContainsString( 'EERB_DB::get_bookings', $source, 'Bookings view must call EERB_DB::get_bookings()' );
        $this->assertStringContainsString( 'erb-table',            $source, 'Bookings view must render the bookings table' );
        $this->assertStringContainsString( 'wp_add_inline_script', $source, 'Bookings view must use wp_add_inline_script for JS' );
    }

    public function test_customers_view_has_query_structure() {
        $source = file_get_contents( EERB_PLUGIN_DIR . 'admin/views/customers.php' );
        $this->assertStringContainsString( 'eerb_customers',  $source, 'Customers view must query erb_customers table' );
        $this->assertStringContainsString( 'erb-table',      $source, 'Customers view must render the customers table' );
        $this->assertStringContainsString( 'booking_count',  $source, 'Customers view must show booking count' );
    }

    // ── No raw $sql variables in views ────────────────────────────────────────

    public function test_no_raw_sql_variables_in_views() {
        foreach ( $this->get_view_files() as $file ) {
            $source = file_get_contents( EERB_PLUGIN_DIR . $file );
            $lines  = explode( "\n", $source );
            foreach ( $lines as $i => $line ) {
                $trimmed = ltrim( $line );
                if ( 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '*' ) ) continue;
                $this->assertStringNotContainsString(
                    '$sql =',
                    $line,
                    "View file {$file} line " . ( $i + 1 ) . " contains a raw \$sql variable"
                );
            }
        }
    }

    // ── Inline scripts use wp_add_inline_script ───────────────────────────────

    public function test_no_bare_script_tags_in_views() {
        // booking.php and bookings.php previously had bare <script> tags.
        // They must now use wp_add_inline_script instead.
        $files_with_js = [
            'admin/views/bookings.php',
            'public/views/booking.php',
        ];
        foreach ( $files_with_js as $file ) {
            $source = file_get_contents( EERB_PLUGIN_DIR . $file );
            $this->assertStringNotContainsString(
                '<script>',
                $source,
                "View file {$file} must not contain bare <script> tags — use wp_add_inline_script()"
            );
        }
    }
}
