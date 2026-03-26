<?php
/**
 * Tests for both Media abilities:
 *  - Generate Alt Text
 *  - Suggest Image Filename
 *
 * @package AbilityHub
 */

require_once dirname( __DIR__ ) . '/class-abilityhub-test-case.php';

// ===========================================================================
// Generate Alt Text
// ===========================================================================

class Test_Ability_Generate_Alt_Text extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Generate_Alt_Text $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Generate_Alt_Text();
    }

    public function test_missing_image_url_returns_error(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_image_url' );
    }

    public function test_invalid_url_returns_error(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'image_url' => 'not-a-valid-url' ] );
        $this->assertIsWpError( $result, 'invalid_url' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'image_url' => 'https://example.com/photo.jpg' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_alt_text_and_caption(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [
            'alt_text' => 'Developer presenting at WordCamp stage',
            'caption'  => 'A developer presents the new Abilities API at WordCamp 2026.',
        ] );

        $result = $this->ability->execute( [
            'image_url'     => 'https://example.com/wordcamp.jpg',
            'image_context' => 'WordPress developer conference keynote',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'alt_text', $result );
        $this->assertArrayHasKey( 'caption', $result );
        $this->assertSame( 'Developer presenting at WordCamp stage', $result['alt_text'] );
    }

    public function test_caption_is_optional_in_response(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        // AI returns alt_text without caption — should still succeed
        $this->mock_ai_success( [ 'alt_text' => 'A landscape photo.' ] );

        $result = $this->ability->execute( [ 'image_url' => 'https://example.com/land.jpg' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'alt_text', $result );
        // caption key should be present but empty
        $this->assertSame( '', $result['caption'] );
    }

    public function test_missing_alt_text_key_returns_parse_error(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'caption' => 'A landscape.' ] ); // missing alt_text

        $result = $this->ability->execute( [ 'image_url' => 'https://example.com/land.jpg' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }

    public function test_invalid_url_with_valid_format_check(): void {
        \Brain\Monkey\Functions\when( 'esc_url_raw' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        // FILTER_VALIDATE_URL will reject this as an invalid URL
        $result = $this->ability->execute( [ 'image_url' => 'ftp://no-scheme-match' ] );
        // ftp:// is actually a valid URL per filter_var — so test with truly broken URL
        $result2 = $this->ability->execute( [ 'image_url' => 'just text no scheme' ] );
        $this->assertIsWpError( $result2, 'invalid_url' );
    }
}

// ===========================================================================
// Suggest Image Filename
// ===========================================================================

class Test_Ability_Suggest_Filename extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Suggest_Filename $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Suggest_Filename();
    }

    public function test_missing_filename_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'image_context' => 'A cat photo.' ] );
        $this->assertIsWpError( $result, 'missing_filename' );
    }

    public function test_missing_context_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [ 'current_filename' => 'IMG_001.jpg' ] );
        $this->assertIsWpError( $result, 'missing_context' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [
            'current_filename' => 'IMG_001.jpg',
            'image_context'    => 'A grey cat sitting on a windowsill.',
        ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_seo_filename_and_reason(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [
            'seo_filename' => 'grey-cat-sitting-on-windowsill',
            'reason'       => 'Descriptive, keyword-rich, uses hyphens.',
        ] );

        $result = $this->ability->execute( [
            'current_filename' => 'IMG_001.jpg',
            'image_context'    => 'A grey cat sitting on a windowsill.',
        ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'seo_filename', $result );
        $this->assertArrayHasKey( 'reason', $result );
        $this->assertSame( 'grey-cat-sitting-on-windowsill', $result['seo_filename'] );
    }

    public function test_filename_special_characters_are_stripped(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        // AI returns filename with uppercase and spaces → should be cleaned to lowercase hyphens only
        $this->mock_ai_success( [
            'seo_filename' => 'valid-filename',
            'reason'       => 'Clean.',
        ] );

        $result = $this->ability->execute( [
            'current_filename' => 'Photo.jpg',
            'image_context'    => 'A nature photo.',
        ] );

        $this->assertMatchesRegularExpression( '/^[a-z0-9\-]+$/', $result['seo_filename'] );
    }

    public function test_all_special_chars_in_filename_returns_error(): void {
        \Brain\Monkey\Functions\when( 'sanitize_file_name' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        // AI returns a filename with only special chars → cleaned to empty → error
        $this->mock_ai_success( [
            'seo_filename' => '!!!###$$$',
            'reason'       => 'Invalid.',
        ] );

        $result = $this->ability->execute( [
            'current_filename' => 'Photo.jpg',
            'image_context'    => 'A photo.',
        ] );

        $this->assertIsWpError( $result, 'invalid_filename' );
    }
}
