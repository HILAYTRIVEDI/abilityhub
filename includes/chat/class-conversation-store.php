<?php
/**
 * Stores and retrieves per-user chat conversation history in user meta.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Conversation_Store {

	const META_KEY     = '_abilityhub_chat_history';
	const MAX_MESSAGES = 50;

	/**
	 * Load the full conversation history for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array{role: string, content: string, time: int}>
	 */
	public function load( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Append one message and persist the updated history.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $role    'user' or 'assistant'.
	 * @param string $content Message text.
	 * @return void
	 */
	public function append( int $user_id, string $role, string $content ): void {
		$history   = $this->load( $user_id );
		$history[] = [
			'role'    => $role,
			'content' => $content,
			'time'    => time(),
		];

		if ( count( $history ) > self::MAX_MESSAGES ) {
			$history = array_slice( $history, -self::MAX_MESSAGES );
		}

		update_user_meta( $user_id, self::META_KEY, $history );
	}

	/**
	 * Clear all conversation history for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public function clear( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Return history formatted for wp_ai_client_prompt()->with_history().
	 *
	 * Strips timestamps and limits to the most recent N user/assistant turns.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $max_pairs Number of recent exchange pairs to include.
	 * @return array<int, array{role: string, content: string}>
	 */
	public function get_history_for_ai( int $user_id, int $max_pairs = 10 ): array {
		$history = $this->load( $user_id );

		$formatted = array_map(
			static fn( $m ) => [ 'role' => $m['role'], 'content' => $m['content'] ],
			$history
		);

		$limit = $max_pairs * 2;
		if ( count( $formatted ) > $limit ) {
			$formatted = array_slice( $formatted, -$limit );
		}

		return $formatted;
	}
}
