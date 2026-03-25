<?php
/**
 * Scans the WordPress site and collects context used in chat system prompts.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Site_Scanner {

	/**
	 * Gather a full site summary for use in AI prompts.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		return [
			'site_name'             => get_bloginfo( 'name' ),
			'site_url'              => get_bloginfo( 'url' ),
			'wp_version'            => get_bloginfo( 'version' ),
			'active_theme'          => wp_get_theme()->get( 'Name' ),
			'post_counts'           => $this->get_post_counts(),
			'active_plugins'        => $this->get_active_plugins(),
			'woocommerce'           => $this->get_woocommerce_stats(),
			'registered_abilities'  => $this->get_ability_names(),
			'registered_workflows'  => $this->get_workflow_ids(),
		];
	}

	/**
	 * Get published post counts keyed by post type.
	 *
	 * @return array<string, int>
	 */
	private function get_post_counts(): array {
		$counts = [];
		$types  = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $types as $type ) {
			$result          = wp_count_posts( $type );
			$counts[ $type ] = (int) ( $result->publish ?? 0 );
		}

		return $counts;
	}

	/**
	 * Get active plugin display names (not file paths).
	 *
	 * @return string[]
	 */
	private function get_active_plugins(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = get_option( 'active_plugins', [] );
		$names  = [];

		foreach ( $active as $plugin_file ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			$data = get_plugin_data( $path, false, false );
			if ( ! empty( $data['Name'] ) ) {
				$names[] = $data['Name'];
			}
		}

		return $names;
	}

	/**
	 * Get WooCommerce-specific stats if WooCommerce is active.
	 *
	 * @return array<string, int>|null Null when WooCommerce is not active.
	 */
	private function get_woocommerce_stats(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		$product_count = wp_count_posts( 'product' );
		$order_count   = wp_count_posts( 'shop_order' );

		return [
			'products'       => (int) ( $product_count->publish     ?? 0 ),
			'pending_orders' => (int) ( $order_count->{'wc-pending'} ?? 0 ),
		];
	}

	/**
	 * Get the names of all registered abilities.
	 *
	 * @return string[]
	 */
	private function get_ability_names(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		return array_keys( wp_get_abilities() );
	}

	/**
	 * Get the IDs of all registered workflows.
	 *
	 * @return string[]
	 */
	private function get_workflow_ids(): array {
		if ( ! function_exists( 'abilityhub_get_workflows' ) ) {
			return [];
		}

		return array_map(
			static fn( $w ) => $w->get_id(),
			abilityhub_get_workflows()
		);
	}
}
