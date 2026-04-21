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
		$cache_key = 'abilityhub_system_instr';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$ctx = $this->scanner->scan();

		$abilities_list = empty( $ctx['registered_abilities'] )
			? '  (none registered)'
			: implode( "\n", array_map( static fn( $a ) => "  - {$a}", $ctx['registered_abilities'] ) );

		$workflows_list = empty( $ctx['registered_workflows'] )
			? '  (none registered)'
			: implode( "\n", array_map( static fn( $w ) => "  - {$w}", $ctx['registered_workflows'] ) );

		$woo_line = '';
		if ( $ctx['woocommerce'] ) {
			$woo_line = sprintf(
				"\n- WooCommerce: active (%d published products, %d pending orders)",
				$ctx['woocommerce']['products'],
				$ctx['woocommerce']['pending_orders']
			);
		}

		$post_lines = '';
		foreach ( $ctx['post_counts'] as $type => $count ) {
			$post_lines .= "  {$type}: {$count}\n";
		}

		$instruction = <<<SYSTEM
You are AbilityOperator, the AI Site Operator for the WordPress site "{$ctx['site_name']}" ({$ctx['site_url']}).
You help administrators automate tasks using the site's registered AI abilities and workflows.
Always refer to yourself as AbilityOperator, not by the site name.

## Site context
- WordPress {$ctx['wp_version']} — theme: {$ctx['active_theme']}{$woo_line}

## Published content
{$post_lines}
## Registered abilities
{$abilities_list}

## Registered workflows
{$workflows_list}

## Instructions
Answer questions about the site and its AI capabilities concisely.
When the user asks you to perform an action, reply with a plain explanation AND append exactly one
JSON intent block inside a fenced code block at the very end of your message.

### Intent format

run_ability — execute a single ability once:
```json
{"intent":"run_ability","ability":"<ability-name>","input":{}}
```

batch_process — run an ability on multiple posts:
```json
{"intent":"batch_process","ability":"<ability-name>","post_type":"post","post_status":"publish","limit":10,"input_map":{"content_field":"post_content"}}
```

create_workflow — register a new event-driven workflow:
```json
{"intent":"create_workflow","workflow_id":"<id>","trigger":"post_published","chain":["<ability>"],"require_approval":true}
```

deactivate_workflow — pause an existing workflow:
```json
{"intent":"deactivate_workflow","workflow_id":"<id>"}
```

activate_workflow — resume a paused workflow:
```json
{"intent":"activate_workflow","workflow_id":"<id>"}
```

If no action is needed, respond conversationally without a JSON block.
SYSTEM;

		set_transient( $cache_key, $instruction, 5 * MINUTE_IN_SECONDS );

		return $instruction;
	}
}
