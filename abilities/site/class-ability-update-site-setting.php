<?php
/**
 * Ability: Update Site Setting
 *
 * Updates a WordPress option from a safe whitelist.
 * No AI call — pure WordPress option management.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Ability_Update_Site_Setting extends AbilityHub_Ability_Base {

	protected string $name        = 'abilityhub/update-site-setting';
	protected string $label       = 'Update site setting';
	protected string $description = 'Updates a WordPress site setting such as site title, tagline, timezone, reading settings, comment settings, or permalink structure. Requires admin privileges.';
	protected string $category    = 'site';
	protected bool   $cacheable   = false;

	protected array $input_schema = [
		'type'     => 'object',
		'required' => [ 'setting', 'value' ],
		'properties' => [
			'setting' => [
				'type'        => 'string',
				'description' => 'The option key to update. Available: blogname, blogdescription, admin_email, timezone_string, date_format, time_format, start_of_week, posts_per_page, posts_per_rss, default_ping_status, default_comment_status, comment_moderation, close_comments_for_old_posts, close_comments_days_old, thread_comments, thread_comments_depth, permalink_structure, show_on_front, page_on_front, page_for_posts, default_category.',
			],
			'value' => [
				'type'        => 'string',
				'description' => 'The new value to set.',
			],
		],
	];

	protected array $output_schema = [
		'type'     => 'object',
		'required' => [ 'success', 'setting', 'new_value' ],
		'properties' => [
			'success'        => [ 'type' => 'boolean' ],
			'setting'        => [ 'type' => 'string' ],
			'setting_label'  => [ 'type' => 'string' ],
			'previous_value' => [ 'type' => 'string' ],
			'new_value'      => [ 'type' => 'string' ],
		],
	];

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Allowed settings with their human labels and sanitization callbacks.
	 * Only settings in this list can be modified.
	 *
	 * @return array<string, array{label: string, sanitize: callable}>
	 */
	private static function allowed_settings(): array {
		return [
			'blogname'                     => [ 'label' => 'Site title',                       'sanitize' => 'sanitize_text_field' ],
			'blogdescription'              => [ 'label' => 'Tagline',                          'sanitize' => 'sanitize_text_field' ],
			'admin_email'                  => [ 'label' => 'Admin email address',              'sanitize' => 'sanitize_email' ],
			'timezone_string'              => [ 'label' => 'Timezone',                         'sanitize' => 'sanitize_text_field' ],
			'date_format'                  => [ 'label' => 'Date format',                      'sanitize' => 'sanitize_text_field' ],
			'time_format'                  => [ 'label' => 'Time format',                      'sanitize' => 'sanitize_text_field' ],
			'start_of_week'                => [ 'label' => 'Week starts on (0=Sun, 1=Mon…)',   'sanitize' => 'absint' ],
			'posts_per_page'               => [ 'label' => 'Posts per page',                   'sanitize' => 'absint' ],
			'posts_per_rss'                => [ 'label' => 'Syndication feeds posts to show',  'sanitize' => 'absint' ],
			'default_ping_status'          => [ 'label' => 'Pingbacks & trackbacks (open/closed)', 'sanitize' => 'sanitize_key' ],
			'default_comment_status'       => [ 'label' => 'Allow comments on new posts (open/closed)', 'sanitize' => 'sanitize_key' ],
			'comment_moderation'           => [ 'label' => 'Hold comments for moderation (1=yes, 0=no)', 'sanitize' => 'absint' ],
			'close_comments_for_old_posts' => [ 'label' => 'Close comments on old posts (1=yes, 0=no)',  'sanitize' => 'absint' ],
			'close_comments_days_old'      => [ 'label' => 'Close comments after N days',      'sanitize' => 'absint' ],
			'thread_comments'              => [ 'label' => 'Threaded comments (1=yes, 0=no)',  'sanitize' => 'absint' ],
			'thread_comments_depth'        => [ 'label' => 'Max comment thread depth',         'sanitize' => 'absint' ],
			'permalink_structure'          => [ 'label' => 'Permalink structure',              'sanitize' => 'sanitize_text_field' ],
			'show_on_front'                => [ 'label' => 'Homepage shows (posts/page)',       'sanitize' => 'sanitize_key' ],
			'page_on_front'                => [ 'label' => 'Static front page (post ID)',       'sanitize' => 'absint' ],
			'page_for_posts'               => [ 'label' => 'Posts page (post ID)',              'sanitize' => 'absint' ],
			'default_category'             => [ 'label' => 'Default post category (term ID)',   'sanitize' => 'absint' ],
		];
	}

	public function execute( array $input ): array|WP_Error {
		$start   = $this->start_timer();
		$setting = sanitize_key( $input['setting'] ?? '' );
		$value   = $input['value'] ?? '';

		if ( empty( $setting ) ) {
			return new WP_Error( 'missing_setting', __( 'Setting key is required.', 'abilityhub' ) );
		}

		$allowed = self::allowed_settings();

		if ( ! array_key_exists( $setting, $allowed ) ) {
			$keys = implode( ', ', array_keys( $allowed ) );
			return new WP_Error(
				'setting_not_allowed',
				sprintf(
					/* translators: 1: requested setting, 2: comma-separated list of allowed settings */
					__( 'Setting "%1$s" is not modifiable via this ability. Allowed settings: %2$s', 'abilityhub' ),
					$setting,
					$keys
				)
			);
		}

		$meta       = $allowed[ $setting ];
		$sanitize   = $meta['sanitize'];
		$label      = $meta['label'];
		$prev_value = (string) get_option( $setting );

		$clean_value = $sanitize( $value );

		// Extra validation for specific settings
		if ( 'admin_email' === $setting && ! is_email( $clean_value ) ) {
			return new WP_Error( 'invalid_email', __( 'The admin email address is not valid.', 'abilityhub' ) );
		}

		if ( 'timezone_string' === $setting ) {
			$valid_zones = timezone_identifiers_list();
			if ( ! in_array( $clean_value, $valid_zones, true ) && ! empty( $clean_value ) ) {
				return new WP_Error(
					'invalid_timezone',
					sprintf(
						/* translators: %s: provided timezone string */
						__( 'Invalid timezone "%s". Use a valid PHP timezone identifier (e.g. America/New_York).', 'abilityhub' ),
						$clean_value
					)
				);
			}
		}

		update_option( $setting, $clean_value );

		// Flush rewrite rules when permalink structure changes.
		if ( 'permalink_structure' === $setting ) {
			flush_rewrite_rules();
		}

		$new_value = (string) get_option( $setting );

		$this->log( 'success', $this->elapsed_ms( $start ) );

		return [
			'success'        => true,
			'setting'        => $setting,
			'setting_label'  => $label,
			'previous_value' => $prev_value,
			'new_value'      => $new_value,
		];
	}
}
