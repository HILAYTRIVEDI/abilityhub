<?php
/**
 * Tests for all 4 WooCommerce abilities:
 *  - Generate Product Description
 *  - Write Review Response
 *  - Generate Upsell Copy
 *  - Moderate Comment
 *
 * @package AbilityHub
 */

require_once dirname( __DIR__ ) . '/class-abilityhub-test-case.php';

// ===========================================================================
// Generate Product Description
// ===========================================================================

class Test_Ability_Product_Description extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Product_Description $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Product_Description();
    }

    public function test_returns_error_when_woocommerce_inactive(): void {
        // WooCommerce class is not defined → class_exists returns false
        $result = $this->ability->execute( [ 'product_name' => 'Widget' ] );
        $this->assertIsWpError( $result, 'woocommerce_inactive' );
    }

    public function test_missing_product_name_returns_error(): void {
        // Simulate WooCommerce active by defining the class stub
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_product_name' );
    }

    public function test_ai_unavailable_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'product_name' => 'Ergonomic Chair' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_three_descriptions(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        $this->mock_ai_success( [
            'short_description' => 'Comfort meets productivity.',
            'long_description'  => 'The Ergonomic Chair offers superior lumbar support...',
            'meta_description'  => 'Buy the best ergonomic office chair today.',
        ] );

        $result = $this->ability->execute( [
            'product_name' => 'Ergonomic Chair',
            'attributes'   => 'color: black, material: mesh',
            'keyword'      => 'ergonomic office chair',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'short_description', $result );
        $this->assertArrayHasKey( 'long_description', $result );
        $this->assertArrayHasKey( 'meta_description', $result );
    }

    public function test_parse_error_on_incomplete_ai_response(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'short_description' => 'Only one field.' ] );

        $result = $this->ability->execute( [ 'product_name' => 'Widget' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }
}

// ===========================================================================
// Write Review Response
// ===========================================================================

class Test_Ability_Review_Response extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Review_Response $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Review_Response();
    }

    public function test_returns_error_when_woocommerce_inactive(): void {
        // Ensure WooCommerce is considered inactive — if the class was defined above
        // the woocommerce_inactive check fires first, so skip if WC loaded.
        if ( class_exists( 'WooCommerce' ) ) {
            $this->markTestSkipped( 'WooCommerce class is already loaded from a prior test.' );
        }
        $result = $this->ability->execute( [ 'review_text' => 'Great!', 'rating' => 5, 'product_name' => 'Chair' ] );
        $this->assertIsWpError( $result, 'woocommerce_inactive' );
    }

    public function test_missing_review_text_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

        $result = $this->ability->execute( [ 'rating' => 5, 'product_name' => 'Chair' ] );
        $this->assertIsWpError( $result, 'missing_review' );
    }

    public function test_invalid_rating_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

        $result = $this->ability->execute( [ 'review_text' => 'Good!', 'rating' => 0, 'product_name' => 'Chair' ] );
        $this->assertIsWpError( $result, 'invalid_rating' );
    }

    public function test_rating_above_5_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

        $result = $this->ability->execute( [ 'review_text' => 'Bad!', 'rating' => 6, 'product_name' => 'Chair' ] );
        $this->assertIsWpError( $result, 'invalid_rating' );
    }

    public function test_successful_execution_returns_response(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        $this->mock_ai_success( [ 'response' => 'Thank you for your kind review! We are thrilled you love your chair.' ] );

        $result = $this->ability->execute( [
            'review_text'  => 'Absolutely love this chair!',
            'rating'       => 5,
            'product_name' => 'Ergonomic Chair',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'response', $result );
        $this->assertStringContainsString( 'Thank you', $result['response'] );
    }
}

// ===========================================================================
// Generate Upsell Copy
// ===========================================================================

class Test_Ability_Upsell_Copy extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Upsell_Copy $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Upsell_Copy();
    }

    public function test_missing_product_name_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [ 'related_products' => [ 'Desk' ] ] );
        $this->assertIsWpError( $result, 'missing_product' );
    }

    public function test_empty_related_products_returns_error(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'product_name' => 'Chair', 'related_products' => [] ] );
        $this->assertIsWpError( $result, 'missing_related' );
    }

    public function test_successful_execution_returns_upsell_copy(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            eval( 'class WooCommerce {}' );
        }
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        $this->mock_ai_success( [
            'upsell_headline' => 'Complete the setup',
            'upsell_body'     => 'Add a Lumbar Cushion for extra support.',
        ] );

        $result = $this->ability->execute( [
            'product_name'     => 'Ergonomic Chair',
            'related_products' => [ 'Lumbar Cushion', 'Adjustable Desk' ],
        ] );

        $this->assertArrayHasKey( 'upsell_headline', $result );
        $this->assertArrayHasKey( 'upsell_body', $result );
    }
}

// ===========================================================================
// Moderate Comment
// ===========================================================================

class Test_Ability_Moderate_Comment extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Moderate_Comment $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Moderate_Comment();
    }

    public function test_missing_comment_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_comment' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'comment_text' => 'Great article!' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_verdict_approve(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'verdict' => 'approve', 'confidence' => 0.95, 'reason' => 'Genuine comment.' ] );

        $result = $this->ability->execute( [ 'comment_text' => 'Really helpful article, thank you!' ] );

        $this->assertSame( 'approve', $result['verdict'] );
        $this->assertSame( 0.95, $result['confidence'] );
    }

    public function test_invalid_verdict_defaults_to_flag(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'verdict' => 'unknown_value', 'confidence' => 0.5, 'reason' => 'Unclear.' ] );

        $result = $this->ability->execute( [ 'comment_text' => 'Some comment.' ] );

        $this->assertSame( 'flag', $result['verdict'], 'Unknown verdicts should fall back to "flag".' );
    }

    public function test_confidence_is_clamped_to_0_1(): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'verdict' => 'spam', 'confidence' => 1.5, 'reason' => 'Spam.' ] );

        $result = $this->ability->execute( [ 'comment_text' => 'Buy cheap meds online!!!' ] );

        $this->assertLessThanOrEqual( 1.0, $result['confidence'] );
        $this->assertGreaterThanOrEqual( 0.0, $result['confidence'] );
    }

    /**
     * @dataProvider verdict_provider
     */
    public function test_each_valid_verdict_is_preserved( string $verdict ): void {
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'verdict' => $verdict, 'confidence' => 0.8, 'reason' => 'Test.' ] );

        $result = $this->ability->execute( [ 'comment_text' => 'A comment.' ] );
        $this->assertSame( $verdict, $result['verdict'] );
    }

    public static function verdict_provider(): array {
        return [ [ 'approve' ], [ 'flag' ], [ 'spam' ] ];
    }
}
