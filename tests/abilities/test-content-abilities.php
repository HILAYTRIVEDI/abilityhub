<?php
/**
 * Tests for all 5 Content abilities:
 *  - Generate Meta Description
 *  - Rewrite Tone
 *  - Summarise Post
 *  - Suggest Internal Links
 *  - Translate Block
 *
 * @package AbilityHub
 */

require_once dirname( __DIR__ ) . '/class-abilityhub-test-case.php';

// ===========================================================================
// Generate Meta Description
// ===========================================================================

class Test_Ability_Generate_Meta extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Generate_Meta $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Generate_Meta();
    }

    public function test_missing_content_returns_wp_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->justReturn( '' );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_content' );
    }

    public function test_ai_unavailable_returns_no_ai_client_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'Some post content here.' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_ai_generate_error_propagates(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        $this->mock_ai_generate_error( 'provider_error', 'Provider failed' );

        $result = $this->ability->execute( [ 'content' => 'Some post content here.' ] );
        $this->assertIsWpError( $result, 'provider_error' );
    }

    public function test_invalid_json_returns_parse_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        $this->mock_ai_invalid_json();

        $result = $this->ability->execute( [ 'content' => 'Some post content here.' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }

    public function test_successful_execution_returns_meta_fields(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [
            'meta_title'       => 'Best WordPress AI Plugin 2026',
            'meta_description' => 'Discover AbilityHub, the AI marketplace for WordPress 7.0.',
        ] );

        $result = $this->ability->execute( [ 'content' => 'WordPress 7.0 ships a new Abilities API.' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'meta_title', $result );
        $this->assertArrayHasKey( 'meta_description', $result );
        $this->assertSame( 'Best WordPress AI Plugin 2026', $result['meta_title'] );
    }

    public function test_missing_output_key_returns_parse_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        // Only one of the two required keys returned
        $this->mock_ai_success( [ 'meta_title' => 'Title Only — missing meta_description' ] );

        $result = $this->ability->execute( [ 'content' => 'Test content.' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }
}

// ===========================================================================
// Rewrite Tone
// ===========================================================================

class Test_Ability_Rewrite_Tone extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Rewrite_Tone $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Rewrite_Tone();
    }

    public function test_missing_content_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'tone' => 'professional' ] );
        $this->assertIsWpError( $result, 'missing_content' );
    }

    public function test_invalid_tone_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'Some text.', 'tone' => 'aggressive' ] );
        $this->assertIsWpError( $result, 'invalid_tone' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'Some text.', 'tone' => 'casual' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_rewritten_content(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        $this->mock_ai_success( [ 'rewritten_content' => 'Professionally rewritten text.' ] );

        $result = $this->ability->execute( [ 'content' => 'Some text.', 'tone' => 'professional' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'rewritten_content', $result );
    }

    /**
     * @dataProvider valid_tones_provider
     */
    public function test_each_valid_tone_is_accepted( string $tone ): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        $this->mock_ai_success( [ 'rewritten_content' => 'Rewritten.' ] );

        $result = $this->ability->execute( [ 'content' => 'Some text.', 'tone' => $tone ] );
        $this->assertArrayHasKey( 'rewritten_content', $result );
    }

    public static function valid_tones_provider(): array {
        return [
            [ 'professional' ],
            [ 'casual' ],
            [ 'friendly' ],
            [ 'authoritative' ],
            [ 'humorous' ],
        ];
    }
}

// ===========================================================================
// Summarise Post
// ===========================================================================

class Test_Ability_Summarise_Post extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Summarise_Post $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Summarise_Post();
    }

    public function test_missing_content_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_content' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'A long post about WordPress.' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_invalid_max_words_resets_to_default(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'summary' => 'A summary.', 'tldr' => 'Short.' ] );

        // max_words = 5 is below the 10 minimum → should reset to 50 and still succeed
        $result = $this->ability->execute( [ 'content' => 'Content here.', 'max_words' => 5 ] );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'summary', $result );
        $this->assertArrayHasKey( 'tldr', $result );
    }

    public function test_successful_execution_returns_summary_and_tldr(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [
            'summary' => 'WordPress 7.0 is a major release.',
            'tldr'    => 'WP 7 ships AI APIs.',
        ] );

        $result = $this->ability->execute( [ 'content' => 'WordPress 7.0 ships new AI APIs.', 'max_words' => 50 ] );

        $this->assertSame( 'WordPress 7.0 is a major release.', $result['summary'] );
        $this->assertSame( 'WP 7 ships AI APIs.', $result['tldr'] );
    }
}

// ===========================================================================
// Suggest Internal Links
// ===========================================================================

class Test_Ability_Suggest_Links extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Suggest_Links $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Suggest_Links();
    }

    public function test_missing_content_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

        $result = $this->ability->execute( [] );
        $this->assertIsWpError( $result, 'missing_content' );
    }

    public function test_no_published_posts_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( [] );

        $result = $this->ability->execute( [ 'content' => 'Some content to link from.' ] );
        $this->assertIsWpError( $result, 'no_posts' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        $post        = new stdClass();
        $post->ID    = 1;
        $post->post_title = 'Example Post';
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( [ $post ] );
        \Brain\Monkey\Functions\when( 'get_post_field' )->justReturn( 'example-post' );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'Some content to link from.' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_suggestions(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        $post             = new stdClass();
        $post->ID         = 10;
        $post->post_title = 'Guide to WordPress AI';
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( [ $post ] );
        \Brain\Monkey\Functions\when( 'get_post_field' )->justReturn( 'guide-to-wordpress-ai' );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_title' )->returnArg();
        $this->mock_ai_success( [
            'suggestions' => [
                [
                    'anchor_text'    => 'WordPress AI',
                    'suggested_slug' => 'guide-to-wordpress-ai',
                    'reason'         => 'Directly related to the topic.',
                ],
            ],
        ] );

        $result = $this->ability->execute( [ 'content' => 'Learn about WordPress AI capabilities.' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'suggestions', $result );
        $this->assertCount( 1, $result['suggestions'] );
        $this->assertSame( 'WordPress AI', $result['suggestions'][0]['anchor_text'] );
    }

    public function test_suggestions_with_missing_keys_are_filtered_out(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
        $post             = new stdClass();
        $post->ID         = 1;
        $post->post_title = 'Post';
        \Brain\Monkey\Functions\when( 'get_posts' )->justReturn( [ $post ] );
        \Brain\Monkey\Functions\when( 'get_post_field' )->justReturn( 'post' );
        \Brain\Monkey\Functions\when( 'wp_trim_words' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_title' )->returnArg();
        $this->mock_ai_success( [
            'suggestions' => [
                [ 'anchor_text' => 'Only anchor text — missing slug and reason' ],
                [ 'anchor_text' => 'Complete', 'suggested_slug' => 'slug', 'reason' => 'Good.' ],
            ],
        ] );

        $result = $this->ability->execute( [ 'content' => 'Content here.' ] );

        $this->assertCount( 1, $result['suggestions'] );
    }
}

// ===========================================================================
// Translate Block
// ===========================================================================

class Test_Ability_Translate_Block extends AbilityHub_Test_Case {

    private AbilityHub_Ability_Translate_Block $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new AbilityHub_Ability_Translate_Block();
    }

    public function test_missing_content_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->justReturn( '' );
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'target_language' => 'es' ] );
        $this->assertIsWpError( $result, 'missing_content' );
    }

    public function test_missing_language_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->justReturn( '' );

        $result = $this->ability->execute( [ 'content' => 'Hello world.' ] );
        $this->assertIsWpError( $result, 'missing_language' );
    }

    public function test_ai_unavailable_returns_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->ability->execute( [ 'content' => 'Hello world.', 'target_language' => 'fr' ] );
        $this->assertIsWpError( $result, 'no_ai_client' );
    }

    public function test_successful_execution_returns_translated_content(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [
            'translated_content' => 'Bonjour le monde.',
            'detected_language'  => 'en',
        ] );

        $result = $this->ability->execute( [ 'content' => 'Hello world.', 'target_language' => 'fr' ] );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'translated_content', $result );
        $this->assertArrayHasKey( 'detected_language', $result );
        $this->assertSame( 'Bonjour le monde.', $result['translated_content'] );
        $this->assertSame( 'en', $result['detected_language'] );
    }

    public function test_missing_output_key_returns_parse_error(): void {
        \Brain\Monkey\Functions\when( 'wp_kses_post' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $this->mock_ai_success( [ 'translated_content' => 'Bonjour.' ] ); // missing detected_language

        $result = $this->ability->execute( [ 'content' => 'Hello.', 'target_language' => 'fr' ] );
        $this->assertIsWpError( $result, 'parse_error' );
    }
}
