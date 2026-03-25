<?php
/**
 * WP_List_Table implementation for the workflow approval queue.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AbilityHub_Approval_List_Table extends WP_List_Table {

	/**
	 * Pending items to display.
	 *
	 * @var WP_Post[]
	 */
	private array $items_data = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Pending Approval', 'abilityhub' ),
			'plural'   => __( 'Pending Approvals', 'abilityhub' ),
			'ajax'     => false,
		] );
	}

	/**
	 * Load data into the table.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$queue            = new AbilityHub_Approval_Queue();
		$this->items_data = $queue->get_pending( 100 );
		$this->items      = $this->items_data;

		$this->set_pagination_args( [
			'total_items' => count( $this->items ),
			'per_page'    => 100,
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Define table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'workflow_id' => __( 'Workflow', 'abilityhub' ),
			'trigger'     => __( 'Trigger', 'abilityhub' ),
			'abilities'   => __( 'Chain', 'abilityhub' ),
			'preview'     => __( 'Result Preview', 'abilityhub' ),
			'created_at'  => __( 'Queued', 'abilityhub' ),
			'actions'     => __( 'Actions', 'abilityhub' ),
		];
	}

	/**
	 * No sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return [];
	}

	/**
	 * Render the workflow ID column.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_workflow_id( WP_Post $item ): string {
		$workflow_id = get_post_meta( $item->ID, '_abilityhub_workflow_id', true );
		return '<strong>' . esc_html( $workflow_id ?: __( '(unknown)', 'abilityhub' ) ) . '</strong>';
	}

	/**
	 * Render the trigger column.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_trigger( WP_Post $item ): string {
		$trigger = get_post_meta( $item->ID, '_abilityhub_trigger', true );
		return '<code>' . esc_html( $trigger ?: '—' ) . '</code>';
	}

	/**
	 * Render the chain (abilities) column.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_abilities( WP_Post $item ): string {
		$workflow_id = get_post_meta( $item->ID, '_abilityhub_workflow_id', true );
		$workflow    = $workflow_id ? abilityhub_get_workflow( (string) $workflow_id ) : null;

		if ( ! $workflow ) {
			return '—';
		}

		$chain = array_map( 'esc_html', $workflow->get_chain() );
		return implode( ' → ', array_map( static fn( $name ) => '<code>' . $name . '</code>', $chain ) );
	}

	/**
	 * Render a preview of the chain results.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_preview( WP_Post $item ): string {
		$results = get_post_meta( $item->ID, '_abilityhub_results', true );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return '<em>' . esc_html__( 'No results', 'abilityhub' ) . '</em>';
		}

		$first  = reset( $results );
		$output = $first['output'] ?? [];

		if ( empty( $output ) ) {
			return '<em>' . esc_html__( 'Empty output', 'abilityhub' ) . '</em>';
		}

		// Show the first value of the first ability's output as a preview.
		$preview = is_array( $output ) ? reset( $output ) : $output;
		$preview = is_string( $preview ) ? wp_trim_words( $preview, 15 ) : __( '(non-text output)', 'abilityhub' );

		return '<span title="' . esc_attr( $first['ability'] ?? '' ) . '">' . esc_html( $preview ) . '</span>';
	}

	/**
	 * Render the queued-at column.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_created_at( WP_Post $item ): string {
		$created_at = get_post_meta( $item->ID, '_abilityhub_created_at', true );
		return esc_html( $created_at ?: $item->post_date );
	}

	/**
	 * Render the approve/reject action buttons.
	 *
	 * @param WP_Post $item Current row item.
	 * @return string
	 */
	public function column_actions( WP_Post $item ): string {
		$approve_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=abilityhub_approve_workflow&post_id=' . $item->ID ),
			'abilityhub_workflow_action_' . $item->ID
		);

		$reject_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=abilityhub_reject_workflow&post_id=' . $item->ID ),
			'abilityhub_workflow_action_' . $item->ID
		);

		return sprintf(
			'<a href="%s" class="button button-primary button-small">%s</a> <a href="%s" class="button button-small abilityhub-reject-btn" onclick="return confirm(\'%s\')">%s</a>',
			esc_url( $approve_url ),
			esc_html__( 'Approve', 'abilityhub' ),
			esc_url( $reject_url ),
			esc_js( __( 'Are you sure you want to reject and discard these results?', 'abilityhub' ) ),
			esc_html__( 'Reject', 'abilityhub' )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param WP_Post $item        Current row item.
	 * @param string  $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return '—';
	}

	/**
	 * Render the message shown when there are no items.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No workflow results are waiting for approval.', 'abilityhub' );
	}
}
