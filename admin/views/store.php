<?php
/**
 * Ability Store view.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

$abilities         = AbilityHub_Admin::get_store_abilities();
$active_filter     = sanitize_key( $_GET['category'] ?? 'all' );

// Extract unique categories
$categories = [ 'all' => __( 'All', 'abilityhub' ) ];
foreach ( $abilities as $ability ) {
    $cat = $ability['category'];
    if ( ! isset( $categories[ $cat ] ) ) {
        $categories[ $cat ] = ucfirst( $cat );
    }
}

// Filter abilities
$filtered = $abilities;
if ( 'all' !== $active_filter ) {
    $filtered = array_filter( $abilities, fn( $a ) => $a['category'] === $active_filter );
}

// Check which abilities are registered (installed)
$registered_names = [];
if ( function_exists( 'wp_get_abilities' ) ) {
    $registered_names = array_keys( wp_get_abilities() );
}

$category_icons = [
    'seo'           => '🔍',
    'editorial'     => '✍️',
    'multilingual'  => '🌐',
    'ecommerce'     => '🛒',
    'moderation'    => '🛡️',
    'developer'     => '💻',
    'accessibility' => '♿',
    'media'         => '🖼️',
    'site'          => '🌐',
];
?>

<div class="abilityhub-store">

    <!-- Store header -->
    <div class="abilityhub-store__header">
        <div>
            <h2><?php esc_html_e( '15 Production-Ready Abilities', 'abilityhub' ); ?></h2>
            <p class="abilityhub-store__subtitle">
                <?php esc_html_e( 'All abilities are built natively on the WordPress 7.0 Abilities API and work with any AI provider.', 'abilityhub' ); ?>
            </p>
        </div>
        <div class="abilityhub-store__registry-btn">
            <button class="button" disabled title="<?php esc_attr_e( 'Coming soon — connect to AbilityHub Registry for community abilities', 'abilityhub' ); ?>">
                🌐 <?php esc_html_e( 'Connect to Registry', 'abilityhub' ); ?>
            </button>
        </div>
    </div>

    <!-- Category filter tabs -->
    <div class="abilityhub-category-filter">
        <?php foreach ( $categories as $slug => $label ) : ?>
            <?php
            $url    = add_query_arg( [ 'page' => 'abilityhub', 'tab' => 'store', 'category' => $slug ], admin_url( 'admin.php' ) );
            $active = $active_filter === $slug ? ' abilityhub-filter-tab--active' : '';
            ?>
            <a href="<?php echo esc_url( $url ); ?>" class="abilityhub-filter-tab<?php echo esc_attr( $active ); ?>">
                <?php echo isset( $category_icons[ $slug ] ) ? esc_html( $category_icons[ $slug ] ) . ' ' : ''; ?>
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Ability grid -->
    <div class="abilityhub-abilities-grid">
        <?php foreach ( $filtered as $ability ) : ?>
            <?php
            $is_installed = in_array( $ability['name'], $registered_names, true );
            $icon         = $category_icons[ $ability['category'] ] ?? '⚡';
            ?>
            <div class="abilityhub-ability-card" data-ability="<?php echo esc_attr( $ability['name'] ); ?>">
                <div class="abilityhub-ability-card__header">
                    <div class="abilityhub-ability-card__icon"><?php echo esc_html( $icon ); ?></div>
                    <span class="abilityhub-badge abilityhub-badge--<?php echo esc_attr( $ability['category'] ); ?>">
                        <?php echo esc_html( ucfirst( $ability['category'] ) ); ?>
                    </span>
                    <?php if ( $is_installed ) : ?>
                        <span class="abilityhub-badge abilityhub-badge--installed">
                            ✓ <?php esc_html_e( 'Active', 'abilityhub' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="abilityhub-ability-card__body">
                    <h3 class="abilityhub-ability-card__name"><?php echo esc_html( $ability['label'] ); ?></h3>
                    <p class="abilityhub-ability-card__slug"><?php echo esc_html( $ability['name'] ); ?></p>
                    <p class="abilityhub-ability-card__description"><?php echo esc_html( $ability['description'] ); ?></p>
                </div>
                <div class="abilityhub-ability-card__footer">
                    <button class="button abilityhub-try-btn"
                            data-ability="<?php echo esc_attr( $ability['name'] ); ?>"
                            data-label="<?php echo esc_attr( $ability['label'] ); ?>"
                            data-example="<?php echo esc_attr( wp_json_encode( $ability['example'] ) ); ?>">
                        <?php esc_html_e( 'Try it', 'abilityhub' ); ?>
                    </button>
                    <a href="<?php echo esc_url( rest_url( 'wp-abilities/v1/abilities/' . rawurlencode( $ability['name'] ) ) ); ?>"
                       target="_blank" class="abilityhub-link" title="<?php esc_attr_e( 'View REST schema', 'abilityhub' ); ?>">
                        REST ↗
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Try it modal -->
<div id="abilityhub-modal" class="abilityhub-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="abilityhub-modal-title">
    <div class="abilityhub-modal__backdrop"></div>
    <div class="abilityhub-modal__content">
        <div class="abilityhub-modal__header">
            <h2 id="abilityhub-modal-title" class="abilityhub-modal__title"></h2>
            <button class="abilityhub-modal__close" aria-label="<?php esc_attr_e( 'Close', 'abilityhub' ); ?>">✕</button>
        </div>
        <div class="abilityhub-modal__body">
            <div class="abilityhub-field">
                <label for="modal-input"><?php esc_html_e( 'Input (JSON)', 'abilityhub' ); ?></label>
                <textarea id="modal-input" class="abilityhub-textarea abilityhub-textarea--code" rows="8"></textarea>
            </div>
            <button id="modal-execute" class="button button-primary abilityhub-button">
                <?php esc_html_e( 'Execute', 'abilityhub' ); ?>
            </button>
            <div id="modal-output" style="display:none;" class="abilityhub-modal__output">
                <div class="abilityhub-output-header">
                    <span id="modal-status" class="abilityhub-output-label"></span>
                    <span id="modal-duration" class="abilityhub-output-meta"></span>
                    <button class="abilityhub-copy-btn" data-target="modal-result"><?php esc_html_e( 'Copy', 'abilityhub' ); ?></button>
                </div>
                <pre id="modal-result" class="abilityhub-output-pre"></pre>
            </div>
        </div>
    </div>
</div>
