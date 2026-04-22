<?php
/**
 * Builds the system prompt for the AI Site Operator chat.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Prompt_Builder {

	/** @var AbilityHub_Site_Scanner */
	private AbilityHub_Site_Scanner $scanner;

	public function __construct( AbilityHub_Site_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Build the system instruction that primes the AI with site context and the
	 * structured intent protocol the chat handler expects.
	 *
	 * The result is cached for 5 minutes to avoid redundant DB queries on every
	 * chat turn (post counts, plugin list, WooCommerce stats, etc.).
	 *
	 * @return string
	 */
	public function build_system_instruction(): string {
		$cache_key = 'abilityhub_system_instr_v6';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$ctx = $this->scanner->scan();

		// Single-line comma-separated abilities (fewer tokens than one-per-line).
		$abilities_list = empty( $ctx['registered_abilities'] )
			? '(none)'
			: implode( ', ', $ctx['registered_abilities'] );

		// Only include workflows section when workflows exist.
		$workflows_section = '';
		if ( ! empty( $ctx['registered_workflows'] ) ) {
			$workflows_section = 'Workflows: ' . implode( ', ', $ctx['registered_workflows'] ) . "\n";
		}

		$woo_line = '';
		if ( $ctx['woocommerce'] ) {
			$woo_line = sprintf(
				" WooCommerce: %d products, %d pending orders.",
				$ctx['woocommerce']['products'],
				$ctx['woocommerce']['pending_orders']
			);
		}

		// Single-line post counts (e.g. "post: 12, page: 4").
		$post_summary = empty( $ctx['post_counts'] )
			? '(none)'
			: implode( ', ', array_map(
				static fn( $t, $n ) => "{$t}: {$n}",
				array_keys( $ctx['post_counts'] ),
				$ctx['post_counts']
			) );

		$instruction = <<<SYSTEM
You are AbilityOperator for "{$ctx['site_name']}" ({$ctx['site_url']}). WP {$ctx['wp_version']}, theme: {$ctx['active_theme']}.{$woo_line}
Content: {$post_summary}
Abilities: {$abilities_list}
{$workflows_section}
When asked to act, reply briefly and append ONE JSON intent block at the end.
Messages prefixed "[AbilityHub]" are system-injected ability results — use the data to continue.

Intent formats:
run_ability: {"intent":"run_ability","ability":"NAME","input":{}}
batch_process: {"intent":"batch_process","ability":"NAME","post_type":"post","post_status":"publish","limit":10,"input_map":{}}
create_workflow: {"intent":"create_workflow","workflow_id":"ID","trigger":"post_published","chain":["ABILITY"],"require_approval":true}
deactivate_workflow: {"intent":"deactivate_workflow","workflow_id":"ID"}
activate_workflow: {"intent":"activate_workflow","workflow_id":"ID"}

Key abilities:
- abilityhub/get-posts: find posts by type/status/search → returns ids, titles, urls
- abilityhub/manage-post: action=create|update|publish|draft|trash, post_id required for non-create
- abilityhub/update-site-setting: setting=blogname|blogdescription|admin_email|timezone_string|date_format|time_format|start_of_week|posts_per_page|default_comment_status|comment_moderation|permalink_structure|show_on_front|page_on_front|page_for_posts|default_category, value=...
- abilityhub/classify-content: suggest tags/categories for a post → post_id required, taxonomies (optional array), max_suggestions (optional, default 5)
- abilityhub/fetch-url: fetch real-time content from any public URL → url required, format=auto|rss|text|json (optional). Use for news, RSS feeds, live data, web pages.

Rules:
- Use get-posts first when you need a post_id.
- Confirm before trash/publish if user was vague.
- For real-time data (news, prices, live info): use abilityhub/fetch-url with a relevant public URL or RSS feed. You may construct RSS feed URLs from known sources (e.g. TechCrunch, BBC, WordPress.org).
- Refuse without any JSON block: generating malware or attack code, assisting with illegal activity, fetching URLs for harmful purposes, creating deceptive or impersonating content.
- No JSON block for casual conversational replies.
SYSTEM;

		delete_transient( 'abilityhub_system_instr' );    // Remove old cache keys.
		delete_transient( 'abilityhub_system_instr_v2' );
		delete_transient( 'abilityhub_system_instr_v3' );
		delete_transient( 'abilityhub_system_instr_v4' );
		delete_transient( 'abilityhub_system_instr_v5' );
		set_transient( $cache_key, $instruction, 5 * MINUTE_IN_SECONDS );

		return $instruction;
	}
}
