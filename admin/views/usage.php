<?php
/**
 * Token Usage view.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

// ---- Filters ----------------------------------------------------------------
$filter_provider   = sanitize_key( $_GET['filter_provider']   ?? '' );
$filter_ability    = sanitize_text_field( $_GET['filter_ability']    ?? '' );
$filter_capability = sanitize_key( $_GET['filter_capability'] ?? '' );
$filter_period     = sanitize_key( $_GET['period']            ?? '30' );
$filter_date_from  = sanitize_text_field( $_GET['date_from']  ?? '' );
$filter_date_to    = sanitize_text_field( $_GET['date_to']    ?? '' );
$current_page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page          = 25;

// Resolve date range from period shortcut
$today = gmdate( 'Y-m-d' );
if ( 'custom' !== $filter_period && empty( $filter_date_from ) ) {
	if ( 'today' === $filter_period ) {
		$filter_date_from = $today;
		$filter_date_to   = $today;
	} elseif ( is_numeric( $filter_period ) ) {
		$filter_date_from = gmdate( 'Y-m-d', strtotime( "-{$filter_period} days" ) );
		$filter_date_to   = $today;
	}
}

$date_args = [
	'date_from' => $filter_date_from,
	'date_to'   => $filter_date_to,
];

// ---- Data -------------------------------------------------------------------
$grand_totals = AbilityHub_Token_Tracker::get_grand_totals( $date_args );
$by_provider  = AbilityHub_Token_Tracker::get_totals_by_provider( $date_args );
$by_ability   = AbilityHub_Token_Tracker::get_totals_by_ability( array_merge( $date_args, [
	'provider_id' => $filter_provider,
] ) );

$usage_args = array_merge( $date_args, [
	'per_page'   => $per_page,
	'page'       => $current_page,
	'provider_id' => $filter_provider,
	'ability'    => $filter_ability,
	'capability' => $filter_capability,
] );

$usage       = AbilityHub_Token_Tracker::get_usage( $usage_args );
$total_pages = $usage['total'] > 0 ? ceil( $usage['total'] / $per_page ) : 1;

$providers      = AbilityHub_Token_Tracker::get_distinct_providers();
$all_abilities  = AbilityHub_Token_Tracker::get_distinct_abilities();

$base_url = add_query_arg( [ 'page' => 'abilityhub', 'tab' => 'usage' ], admin_url( 'admin.php' ) );

// ---- Capability labels ------------------------------------------------------
$capability_labels = [
	'text_generation'        => __( 'Text Generation', 'abilityhub' ),
	'image_generation'       => __( 'Image Generation', 'abilityhub' ),
	'text_to_speech_conversion' => __( 'Text to Speech', 'abilityhub' ),
	'speech_generation'      => __( 'Speech Generation', 'abilityhub' ),
	'music_generation'       => __( 'Music Generation', 'abilityhub' ),
	'video_generation'       => __( 'Video Generation', 'abilityhub' ),
	'embedding_generation'   => __( 'Embedding Generation', 'abilityhub' ),
	'chat_history'           => __( 'Chat History', 'abilityhub' ),
];

$capability_icons = [
	'text_generation'           => '💬',
	'image_generation'          => '🖼',
	'text_to_speech_conversion' => '🔊',
	'speech_generation'         => '🎙',
	'music_generation'          => '🎵',
	'video_generation'          => '🎬',
	'embedding_generation'      => '🧬',
	'chat_history'              => '📜',
];

function abilityhub_format_tokens( $n ): string {
	$n = (int) $n;
	if ( $n >= 1000000 ) {
		return number_format( $n / 1000000, 1 ) . 'M';
	}
	if ( $n >= 1000 ) {
		return number_format( $n / 1000, 1 ) . 'K';
	}
	return number_format( $n );
}
?>

<div class="abilityhub-usage">

	<div class="abilityhub-usage__header">
		<h2><?php esc_html_e( 'Token Usage', 'abilityhub' ); ?></h2>
		<p class="abilityhub-usage__subtitle">
			<?php esc_html_e( 'Token consumption across all connected AI providers, updated dynamically from API call events.', 'abilityhub' ); ?>
		</p>
	</div>

	<!-- Period filter shortcuts -->
	<div class="abilityhub-usage__period-bar">
		<?php
		$periods = [
			'today' => __( 'Today', 'abilityhub' ),
			'7'     => __( 'Last 7 days', 'abilityhub' ),
			'30'    => __( 'Last 30 days', 'abilityhub' ),
			'90'    => __( 'Last 90 days', 'abilityhub' ),
			'all'   => __( 'All time', 'abilityhub' ),
			'custom' => __( 'Custom range', 'abilityhub' ),
		];
		foreach ( $periods as $slug => $label ) :
			$url    = add_query_arg( [ 'page' => 'abilityhub', 'tab' => 'usage', 'period' => $slug ], admin_url( 'admin.php' ) );
			$active = $filter_period === $slug ? ' abilityhub-period--active' : '';
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="abilityhub-period-btn<?php echo esc_attr( $active ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Custom date range (shown only when period = custom) -->
	<?php if ( 'custom' === $filter_period ) : ?>
		<div class="abilityhub-filters">
			<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="abilityhub-filters__form">
				<input type="hidden" name="page" value="abilityhub">
				<input type="hidden" name="tab" value="usage">
				<input type="hidden" name="period" value="custom">
				<div class="abilityhub-filters__row">
					<div class="abilityhub-field abilityhub-field--inline">
						<label for="date_from"><?php esc_html_e( 'From', 'abilityhub' ); ?></label>
						<input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
					</div>
					<div class="abilityhub-field abilityhub-field--inline">
						<label for="date_to"><?php esc_html_e( 'To', 'abilityhub' ); ?></label>
						<input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
					</div>
					<button type="submit" class="button"><?php esc_html_e( 'Apply', 'abilityhub' ); ?></button>
				</div>
			</form>
		</div>
	<?php endif; ?>

	<!-- Grand total summary cards -->
	<div class="abilityhub-usage__stats">
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">🔢</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( abilityhub_format_tokens( $grand_totals['total_tokens'] ) ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'Total tokens', 'abilityhub' ); ?></span>
			</div>
		</div>
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">📥</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( abilityhub_format_tokens( $grand_totals['prompt_tokens'] ) ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'Input tokens', 'abilityhub' ); ?></span>
			</div>
		</div>
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">📤</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( abilityhub_format_tokens( $grand_totals['completion_tokens'] ) ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'Output tokens', 'abilityhub' ); ?></span>
			</div>
		</div>
		<?php if ( (int) $grand_totals['thought_tokens'] > 0 ) : ?>
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">🧠</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( abilityhub_format_tokens( $grand_totals['thought_tokens'] ) ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'Reasoning tokens', 'abilityhub' ); ?></span>
			</div>
		</div>
		<?php endif; ?>
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">⚡</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( number_format( (int) $grand_totals['total_calls'] ) ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'API calls', 'abilityhub' ); ?></span>
			</div>
		</div>
		<div class="abilityhub-stat-card">
			<span class="abilityhub-stat-card__icon">🔌</span>
			<div class="abilityhub-stat-card__body">
				<span class="abilityhub-stat-card__value"><?php echo esc_html( (int) $grand_totals['provider_count'] ); ?></span>
				<span class="abilityhub-stat-card__label"><?php esc_html_e( 'Providers', 'abilityhub' ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( empty( $by_provider ) ) : ?>

		<div class="abilityhub-empty-state">
			<span class="abilityhub-empty-state__icon">📊</span>
			<h3><?php esc_html_e( 'No usage data yet', 'abilityhub' ); ?></h3>
			<p><?php esc_html_e( 'Token usage will appear here once AI abilities or the AI Operator are used.', 'abilityhub' ); ?></p>
		</div>

	<?php else : ?>

		<!-- Provider breakdown -->
		<div class="abilityhub-usage__section">
			<h3><?php esc_html_e( 'Usage by Provider & Type', 'abilityhub' ); ?></h3>
			<div class="abilityhub-table-wrapper">
				<table class="widefat abilityhub-table abilityhub-table--usage">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Provider', 'abilityhub' ); ?></th>
							<th><?php esc_html_e( 'Type', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Input tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Output tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Total tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'API calls', 'abilityhub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $by_provider as $row ) :
							$cap_label = $capability_labels[ $row['capability'] ] ?? ucwords( str_replace( '_', ' ', $row['capability'] ) );
							$cap_icon  = $capability_icons[ $row['capability'] ] ?? '🔧';
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $row['provider_name'] ?: $row['provider_id'] ); ?></strong>
									<?php if ( $row['provider_id'] ) : ?>
										<code class="abilityhub-code abilityhub-code--small"><?php echo esc_html( $row['provider_id'] ); ?></code>
									<?php endif; ?>
								</td>
								<td>
									<span class="abilityhub-capability-badge">
										<?php echo esc_html( $cap_icon . ' ' . $cap_label ); ?>
									</span>
								</td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['prompt_tokens'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['completion_tokens'] ) ); ?></td>
								<td class="num"><strong><?php echo esc_html( number_format( (int) $row['total_tokens'] ) ); ?></strong></td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['calls'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Per-ability breakdown -->
		<?php if ( ! empty( $by_ability ) ) : ?>
		<div class="abilityhub-usage__section">
			<h3>
				<?php esc_html_e( 'Usage by Ability', 'abilityhub' ); ?>
				<?php if ( $filter_provider ) : ?>
					<span class="abilityhub-filter-badge">
						<?php
						$prov_name = $filter_provider;
						foreach ( $providers as $p ) {
							if ( $p['provider_id'] === $filter_provider ) {
								$prov_name = $p['provider_name'] ?: $filter_provider;
								break;
							}
						}
						echo esc_html( $prov_name );
						?>
						&nbsp;<a href="<?php echo esc_url( add_query_arg( [ 'filter_provider' => '' ], $base_url ) ); ?>" class="abilityhub-filter-badge__remove">✕</a>
					</span>
				<?php endif; ?>
			</h3>
			<div class="abilityhub-table-wrapper">
				<table class="widefat abilityhub-table abilityhub-table--usage">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ability', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Input tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Output tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'Total tokens', 'abilityhub' ); ?></th>
							<th class="num"><?php esc_html_e( 'API calls', 'abilityhub' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $by_ability as $row ) : ?>
							<tr>
								<td>
									<?php if ( 'chat' === $row['ability'] ) : ?>
										<span class="abilityhub-ability-badge abilityhub-ability-badge--chat">
											💬 <?php esc_html_e( 'AI Operator (chat)', 'abilityhub' ); ?>
										</span>
									<?php else : ?>
										<code class="abilityhub-code"><?php echo esc_html( $row['ability'] ); ?></code>
									<?php endif; ?>
								</td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['prompt_tokens'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['completion_tokens'] ) ); ?></td>
								<td class="num"><strong><?php echo esc_html( number_format( (int) $row['total_tokens'] ) ); ?></strong></td>
								<td class="num"><?php echo esc_html( number_format( (int) $row['calls'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php endif; ?>

		<!-- Detailed records table with filters -->
		<div class="abilityhub-usage__section">
			<h3><?php esc_html_e( 'Detailed Records', 'abilityhub' ); ?></h3>

			<div class="abilityhub-filters">
				<form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="abilityhub-filters__form">
					<input type="hidden" name="page" value="abilityhub">
					<input type="hidden" name="tab" value="usage">
					<input type="hidden" name="period" value="<?php echo esc_attr( $filter_period ); ?>">
					<?php if ( $filter_date_from ) : ?>
						<input type="hidden" name="date_from" value="<?php echo esc_attr( $filter_date_from ); ?>">
					<?php endif; ?>
					<?php if ( $filter_date_to ) : ?>
						<input type="hidden" name="date_to" value="<?php echo esc_attr( $filter_date_to ); ?>">
					<?php endif; ?>

					<div class="abilityhub-filters__row">

						<?php if ( ! empty( $providers ) ) : ?>
						<div class="abilityhub-field abilityhub-field--inline">
							<label for="filter_provider"><?php esc_html_e( 'Provider', 'abilityhub' ); ?></label>
							<select id="filter_provider" name="filter_provider">
								<option value=""><?php esc_html_e( 'All providers', 'abilityhub' ); ?></option>
								<?php foreach ( $providers as $p ) : ?>
									<option value="<?php echo esc_attr( $p['provider_id'] ); ?>" <?php selected( $filter_provider, $p['provider_id'] ); ?>>
										<?php echo esc_html( $p['provider_name'] ?: $p['provider_id'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<?php endif; ?>

						<div class="abilityhub-field abilityhub-field--inline">
							<label for="filter_ability"><?php esc_html_e( 'Ability', 'abilityhub' ); ?></label>
							<select id="filter_ability" name="filter_ability">
								<option value=""><?php esc_html_e( 'All abilities', 'abilityhub' ); ?></option>
								<?php foreach ( $all_abilities as $ab ) : ?>
									<option value="<?php echo esc_attr( $ab ); ?>" <?php selected( $filter_ability, $ab ); ?>>
										<?php echo esc_html( 'chat' === $ab ? __( 'AI Operator (chat)', 'abilityhub' ) : $ab ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="abilityhub-field abilityhub-field--inline">
							<label for="filter_capability"><?php esc_html_e( 'Type', 'abilityhub' ); ?></label>
							<select id="filter_capability" name="filter_capability">
								<option value=""><?php esc_html_e( 'All types', 'abilityhub' ); ?></option>
								<?php foreach ( $capability_labels as $cap_key => $cap_label ) : ?>
									<option value="<?php echo esc_attr( $cap_key ); ?>" <?php selected( $filter_capability, $cap_key ); ?>>
										<?php echo esc_html( ( $capability_icons[ $cap_key ] ?? '' ) . ' ' . $cap_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<button type="submit" class="button"><?php esc_html_e( 'Filter', 'abilityhub' ); ?></button>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'abilityhub', 'tab' => 'usage', 'period' => $filter_period ], admin_url( 'admin.php' ) ) ); ?>" class="button">
							<?php esc_html_e( 'Reset', 'abilityhub' ); ?>
						</a>
					</div>
				</form>
			</div>

			<div class="abilityhub-table-meta">
				<?php
				printf(
					/* translators: 1: count shown on page 2: total count */
					esc_html__( 'Showing %1$d of %2$d records', 'abilityhub' ),
					count( $usage['items'] ),
					$usage['total']
				);
				?>
			</div>

			<?php if ( empty( $usage['items'] ) ) : ?>
				<div class="abilityhub-empty-state abilityhub-empty-state--small">
					<p><?php esc_html_e( 'No records match the current filters.', 'abilityhub' ); ?></p>
				</div>
			<?php else : ?>
				<div class="abilityhub-table-wrapper">
					<table class="widefat abilityhub-table abilityhub-table--usage">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Provider', 'abilityhub' ); ?></th>
								<th><?php esc_html_e( 'Model', 'abilityhub' ); ?></th>
								<th><?php esc_html_e( 'Ability', 'abilityhub' ); ?></th>
								<th><?php esc_html_e( 'Type', 'abilityhub' ); ?></th>
								<th class="num"><?php esc_html_e( 'In', 'abilityhub' ); ?></th>
								<th class="num"><?php esc_html_e( 'Out', 'abilityhub' ); ?></th>
								<th class="num"><?php esc_html_e( 'Total', 'abilityhub' ); ?></th>
								<th><?php esc_html_e( 'When', 'abilityhub' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $usage['items'] as $row ) :
								$cap_icon  = $capability_icons[ $row['capability'] ] ?? '🔧';
								$cap_label = $capability_labels[ $row['capability'] ] ?? ucwords( str_replace( '_', ' ', $row['capability'] ) );
								?>
								<tr>
									<td>
										<span title="<?php echo esc_attr( $row['provider_id'] ); ?>">
											<?php echo esc_html( $row['provider_name'] ?: $row['provider_id'] ?: '—' ); ?>
										</span>
									</td>
									<td>
										<span title="<?php echo esc_attr( $row['model_id'] ); ?>">
											<?php echo esc_html( $row['model_name'] ?: $row['model_id'] ?: '—' ); ?>
										</span>
									</td>
									<td>
										<?php if ( 'chat' === $row['ability'] ) : ?>
											<span class="abilityhub-ability-badge abilityhub-ability-badge--chat">💬 <?php esc_html_e( 'Chat', 'abilityhub' ); ?></span>
										<?php else : ?>
											<code class="abilityhub-code abilityhub-code--small"><?php echo esc_html( $row['ability'] ); ?></code>
										<?php endif; ?>
									</td>
									<td>
										<span class="abilityhub-capability-badge" title="<?php echo esc_attr( $row['capability'] ); ?>">
											<?php echo esc_html( $cap_icon . ' ' . $cap_label ); ?>
										</span>
									</td>
									<td class="num"><?php echo esc_html( number_format( (int) $row['prompt_tokens'] ) ); ?></td>
									<td class="num"><?php echo esc_html( number_format( (int) $row['completion_tokens'] ) ); ?></td>
									<td class="num"><strong><?php echo esc_html( number_format( (int) $row['total_tokens'] ) ); ?></strong></td>
									<td>
										<span title="<?php echo esc_attr( $row['created_at'] ); ?>">
											<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['created_at'] ) ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="abilityhub-pagination">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="button">
								&larr; <?php esc_html_e( 'Previous', 'abilityhub' ); ?>
							</a>
						<?php endif; ?>
						<span class="abilityhub-pagination__info">
							<?php
							printf(
								/* translators: 1: current page, 2: total pages */
								esc_html__( 'Page %1$d of %2$d', 'abilityhub' ),
								$current_page,
								$total_pages
							);
							?>
						</span>
						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="button">
								<?php esc_html_e( 'Next', 'abilityhub' ); ?> &rarr;
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	<?php endif; ?>

</div>
