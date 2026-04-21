<?php
/**
 * Token usage tracker — listens to every AI call and records token data.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Token_Tracker {

	/**
	 * Hook callback for wp_ai_client_after_generate_result.
	 * Fired automatically after every WP AI Client generation.
	 *
	 * @param mixed $event AfterGenerateResultEvent instance.
	 */
	public function track( $event ): void {
		if ( ! is_object( $event ) || ! method_exists( $event, 'getResult' ) ) {
			return;
		}

		$result      = $event->getResult();
		$token_usage = method_exists( $result, 'getTokenUsage' ) ? $result->getTokenUsage() : null;

		if ( null === $token_usage ) {
			return;
		}

		$provider_meta = method_exists( $result, 'getProviderMetadata' ) ? $result->getProviderMetadata() : null;
		$model_meta    = method_exists( $result, 'getModelMetadata' )    ? $result->getModelMetadata()    : null;
		$capability    = method_exists( $event, 'getCapability' )         ? $event->getCapability()        : null;

		$cap_string = 'text_generation';
		if ( null !== $capability && method_exists( $capability, '__get' ) ) {
			$cap_string = $capability->value ?? $cap_string;
		} elseif ( is_object( $capability ) && isset( $capability->value ) ) {
			$cap_string = (string) $capability->value;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'abilityhub_token_usage',
			[
				'ability'           => AbilityHub_Ability_Base::$current_ability ?: 'chat',
				'provider_id'       => $provider_meta && method_exists( $provider_meta, 'getId' )   ? (string) $provider_meta->getId()   : '',
				'provider_name'     => $provider_meta && method_exists( $provider_meta, 'getName' ) ? (string) $provider_meta->getName() : '',
				'model_id'          => $model_meta    && method_exists( $model_meta, 'getId' )      ? (string) $model_meta->getId()      : '',
				'model_name'        => $model_meta    && method_exists( $model_meta, 'getName' )    ? (string) $model_meta->getName()    : '',
				'capability'        => $cap_string,
				'prompt_tokens'     => (int) ( method_exists( $token_usage, 'getPromptTokens' )     ? ( $token_usage->getPromptTokens()     ?? 0 ) : 0 ),
				'completion_tokens' => (int) ( method_exists( $token_usage, 'getCompletionTokens' ) ? ( $token_usage->getCompletionTokens() ?? 0 ) : 0 ),
				'total_tokens'      => (int) ( method_exists( $token_usage, 'getTotalTokens' )      ? ( $token_usage->getTotalTokens()      ?? 0 ) : 0 ),
				'thought_tokens'    => (int) ( method_exists( $token_usage, 'getThoughtTokens' )    ? ( $token_usage->getThoughtTokens()    ?? 0 ) : 0 ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ]
		);
	}

	// -------------------------------------------------------------------------
	// Query methods used by admin/views/usage.php
	// -------------------------------------------------------------------------

	/**
	 * Aggregate grand totals (with optional date filter).
	 *
	 * @param array $args Accepts: date_from, date_to.
	 * @return array
	 */
	public static function get_grand_totals( array $args = [] ): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'abilityhub_token_usage';
		$conditions = self::build_date_conditions( $args );
		$where      = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT
				SUM(total_tokens)      AS total_tokens,
				SUM(prompt_tokens)     AS prompt_tokens,
				SUM(completion_tokens) AS completion_tokens,
				SUM(thought_tokens)    AS thought_tokens,
				COUNT(*)               AS total_calls,
				COUNT(DISTINCT provider_id) AS provider_count
			FROM `{$table}` {$where}", ARRAY_A );

		return $row ?: [
			'total_tokens'      => 0,
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'thought_tokens'    => 0,
			'total_calls'       => 0,
			'provider_count'    => 0,
		];
	}

	/**
	 * Token totals grouped by provider + capability.
	 *
	 * @param array $args Accepts: date_from, date_to.
	 * @return array
	 */
	public static function get_totals_by_provider( array $args = [] ): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'abilityhub_token_usage';
		$conditions = self::build_date_conditions( $args );
		$where      = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( "SELECT
				provider_id,
				provider_name,
				capability,
				SUM(prompt_tokens)     AS prompt_tokens,
				SUM(completion_tokens) AS completion_tokens,
				SUM(total_tokens)      AS total_tokens,
				SUM(thought_tokens)    AS thought_tokens,
				COUNT(*)               AS calls
			FROM `{$table}` {$where}
			GROUP BY provider_id, provider_name, capability
			ORDER BY total_tokens DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Token totals grouped by ability.
	 *
	 * @param array $args Accepts: provider_id, date_from, date_to.
	 * @return array
	 */
	public static function get_totals_by_ability( array $args = [] ): array {
		global $wpdb;

		$table      = $wpdb->prefix . 'abilityhub_token_usage';
		$conditions = self::build_date_conditions( $args );

		if ( ! empty( $args['provider_id'] ) ) {
			$conditions[] = $wpdb->prepare( 'provider_id = %s', $args['provider_id'] );
		}

		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( "SELECT
				ability,
				SUM(prompt_tokens)     AS prompt_tokens,
				SUM(completion_tokens) AS completion_tokens,
				SUM(total_tokens)      AS total_tokens,
				COUNT(*)               AS calls
			FROM `{$table}` {$where}
			GROUP BY ability
			ORDER BY total_tokens DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Paginated raw usage records with filters.
	 *
	 * @param array $args Accepts: per_page, page, provider_id, ability, capability, date_from, date_to.
	 * @return array { items: array, total: int }
	 */
	public static function get_usage( array $args = [] ): array {
		global $wpdb;

		$table    = $wpdb->prefix . 'abilityhub_token_usage';
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page']     ?? 1  ) );
		$offset   = ( $page - 1 ) * $per_page;

		$conditions = self::build_date_conditions( $args );

		if ( ! empty( $args['provider_id'] ) ) {
			$conditions[] = $wpdb->prepare( 'provider_id = %s', $args['provider_id'] );
		}
		if ( ! empty( $args['ability'] ) ) {
			$conditions[] = $wpdb->prepare( 'ability = %s', $args['ability'] );
		}
		if ( ! empty( $args['capability'] ) ) {
			$conditions[] = $wpdb->prepare( 'capability = %s', $args['capability'] );
		}

		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		), ARRAY_A ) ?: [];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` {$where}" );

		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * All distinct providers that have usage records.
	 *
	 * @return array  Each row: { provider_id, provider_name }
	 */
	public static function get_distinct_providers(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'abilityhub_token_usage';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( "SELECT DISTINCT provider_id, provider_name FROM `{$table}` ORDER BY provider_name", ARRAY_A ) ?: [];
	}

	/**
	 * All distinct ability slugs that have usage records.
	 *
	 * @return string[]
	 */
	public static function get_distinct_abilities(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'abilityhub_token_usage';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col( "SELECT DISTINCT ability FROM `{$table}` ORDER BY ability" ) ?: [];
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Build prepared WHERE fragments for date_from / date_to args.
	 *
	 * @param array $args
	 * @return string[]
	 */
	private static function build_date_conditions( array $args ): array {
		global $wpdb;

		$conditions = [];

		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		return $conditions;
	}
}
