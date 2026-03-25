<?php
/**
 * Admin view: Workflows — approval queue and registered workflows overview.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

// Handle success/error messages from approve/reject actions.
$action_result = sanitize_key( $_GET['workflow_action'] ?? '' );
$queue         = new AbilityHub_Approval_Queue();
$all_workflows = abilityhub_get_workflows();

// Prepare the list table.
$list_table = new AbilityHub_Approval_List_Table();
$list_table->prepare_items();
?>

<?php if ( 'approved' === $action_result ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Workflow results approved and applied successfully.', 'abilityhub' ); ?></p>
	</div>
<?php elseif ( 'rejected' === $action_result ) : ?>
	<div class="notice notice-info is-dismissible">
		<p><?php esc_html_e( 'Workflow results rejected and discarded.', 'abilityhub' ); ?></p>
	</div>
<?php elseif ( 'error' === $action_result ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( sanitize_text_field( $_GET['workflow_error'] ?? __( 'An error occurred.', 'abilityhub' ) ) ); ?></p>
	</div>
<?php endif; ?>

<div class="abilityhub-section">
	<h2><?php esc_html_e( 'Pending Approvals', 'abilityhub' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Workflow results waiting for your review. Approving applies the results; rejecting discards them.', 'abilityhub' ); ?>
	</p>

	<form method="get">
		<input type="hidden" name="page" value="abilityhub">
		<input type="hidden" name="tab"  value="workflows">
		<?php $list_table->display(); ?>
	</form>
</div>

<div class="abilityhub-section" style="margin-top: 2em;">
	<h2><?php esc_html_e( 'Registered Workflows', 'abilityhub' ); ?></h2>

	<?php if ( empty( $all_workflows ) ) : ?>
		<p>
			<?php esc_html_e( 'No workflows registered yet. Use ', 'abilityhub' ); ?>
			<code>abilityhub_register_workflow()</code>
			<?php esc_html_e( ' in your theme or plugin to create one.', 'abilityhub' ); ?>
		</p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'abilityhub' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'abilityhub' ); ?></th>
					<th><?php esc_html_e( 'Chain', 'abilityhub' ); ?></th>
					<th><?php esc_html_e( 'Requires Approval', 'abilityhub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_workflows as $workflow ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $workflow->get_id() ); ?></strong></td>
						<td><code><?php echo esc_html( $workflow->get_trigger() ); ?></code></td>
						<td>
							<?php
							$chain = $workflow->get_chain();
							echo implode(
								' <span aria-hidden="true">→</span> ',
								array_map(
									static fn( $name ) => '<code>' . esc_html( $name ) . '</code>',
									$chain
								)
							);
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above
							?>
						</td>
						<td>
							<?php echo $workflow->requires_approval()
								? '<span style="color:#d63638">&#x2714; ' . esc_html__( 'Yes', 'abilityhub' ) . '</span>'
								: '<span style="color:#00a32a">&#x2714; ' . esc_html__( 'No (auto-apply)', 'abilityhub' ) . '</span>';
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
