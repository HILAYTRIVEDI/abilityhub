<?php
/**
 * Tests for AbilityHub_Logger.
 *
 * @package AbilityHub
 */

require_once __DIR__ . '/class-abilityhub-test-case.php';

class Test_AbilityHub_Logger extends AbilityHub_Test_Case {

    private Abilityhub_Test_Wpdb $wpdb_mock;

    protected function setUp(): void {
        parent::setUp();
        // Reset the stub wpdb for each test
        $this->wpdb_mock              = new Abilityhub_Test_Wpdb();
        $GLOBALS['wpdb']              = $this->wpdb_mock;
    }

    // -----------------------------------------------------------------------
    // log()
    // -----------------------------------------------------------------------

    public function test_log_skips_insert_when_logging_disabled(): void {
        // Base setUp stubs get_option → 0 (disabled)
        AbilityHub_Logger::log( [ 'ability' => 'test/ability', 'status' => 'success', 'duration_ms' => 100 ] );

        $this->assertEmpty( $this->wpdb_mock->last_insert, 'No insert should occur when logging is disabled.' );
    }

    public function test_log_inserts_row_when_logging_enabled(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( 1 ); // enable logging
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 42 );
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );

        AbilityHub_Logger::log( [
            'ability'     => 'abilityhub/generate-meta-description',
            'status'      => 'success',
            'duration_ms' => 350,
        ] );

        $inserted = $this->wpdb_mock->last_insert;
        $this->assertSame( 'abilityhub/generate-meta-description', $inserted['ability'] );
        $this->assertSame( 'success', $inserted['status'] );
        $this->assertSame( 350, $inserted['duration_ms'] );
        $this->assertSame( 42, $inserted['user_id'] );
    }

    public function test_log_sanitises_status_to_safe_default(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( 1 );
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );

        // Status not provided — should default to 'success'
        AbilityHub_Logger::log( [ 'ability' => 'test/a', 'duration_ms' => 0 ] );

        $this->assertSame( 'success', $this->wpdb_mock->last_insert['status'] );
    }

    // -----------------------------------------------------------------------
    // get_count()
    // -----------------------------------------------------------------------

    public function test_get_count_today_queries_db_and_returns_integer(): void {
        \Brain\Monkey\Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
        // wpdb stub returns '5' from get_var
        $this->wpdb_mock = new class extends Abilityhub_Test_Wpdb {
            public function get_var( string $query ): string { return '5'; }
            public function prepare( string $query, ...$args ): string { return $query; }
        };
        $GLOBALS['wpdb'] = $this->wpdb_mock;

        $count = AbilityHub_Logger::get_count( 'today' );
        $this->assertSame( 5, $count );
    }

    public function test_get_count_week_queries_db(): void {
        $this->wpdb_mock = new class extends Abilityhub_Test_Wpdb {
            public function get_var( string $query ): string { return '17'; }
        };
        $GLOBALS['wpdb'] = $this->wpdb_mock;

        $this->assertSame( 17, AbilityHub_Logger::get_count( 'week' ) );
    }

    public function test_get_count_unknown_period_returns_zero(): void {
        $this->assertSame( 0, AbilityHub_Logger::get_count( 'unknown_period' ) );
    }

    // -----------------------------------------------------------------------
    // get_most_used()
    // -----------------------------------------------------------------------

    public function test_get_most_used_returns_ability_name(): void {
        $this->wpdb_mock = new class extends Abilityhub_Test_Wpdb {
            public function get_var( string $query ): string {
                return 'abilityhub/generate-meta-description';
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb_mock;

        $this->assertSame( 'abilityhub/generate-meta-description', AbilityHub_Logger::get_most_used() );
    }

    public function test_get_most_used_returns_empty_string_when_no_logs(): void {
        // Base stub returns '0' from get_var → cast empty string in real code uses ?? ''
        $this->wpdb_mock = new class extends Abilityhub_Test_Wpdb {
            public function get_var( string $query ): ?string { return null; }
        };
        $GLOBALS['wpdb'] = $this->wpdb_mock;

        $this->assertSame( '', AbilityHub_Logger::get_most_used() );
    }

    // -----------------------------------------------------------------------
    // get_logs()
    // -----------------------------------------------------------------------

    public function test_get_logs_returns_items_and_total_keys(): void {
        $logs = AbilityHub_Logger::get_logs();
        $this->assertArrayHasKey( 'items', $logs );
        $this->assertArrayHasKey( 'total', $logs );
    }

    public function test_get_logs_returns_empty_items_when_no_rows(): void {
        $result = AbilityHub_Logger::get_logs( [ 'per_page' => 10, 'page' => 1 ] );
        $this->assertSame( [], $result['items'] );
        $this->assertSame( 0, $result['total'] );
    }

    // -----------------------------------------------------------------------
    // purge_old_logs()
    // -----------------------------------------------------------------------

    public function test_purge_old_logs_executes_delete_query(): void {
        \Brain\Monkey\Functions\when( 'get_option' )->justReturn( 30 );

        AbilityHub_Logger::purge_old_logs();

        $this->assertNotEmpty( $this->wpdb_mock->queries, 'A DELETE query should have been issued.' );
    }
}
