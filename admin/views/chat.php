<?php
/**
 * Admin view: AI Site Operator chat panel.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="abilityhub-chat-wrap">

	<div class="abilityhub-chat-sidebar">
		<h2 class="abilityhub-chat-sidebar__title">
			<?php esc_html_e( 'AI Site Operator', 'abilityhub' ); ?>
		</h2>
		<p class="abilityhub-chat-sidebar__desc">
			<?php esc_html_e( 'Ask me to run abilities, create workflows, or batch-process your content using natural language.', 'abilityhub' ); ?>
		</p>

		<h3><?php esc_html_e( 'Example prompts', 'abilityhub' ); ?></h3>
		<ul class="abilityhub-chat-examples">
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'How many posts do I have?', 'abilityhub' ); ?>"><?php esc_html_e( 'How many posts do I have?', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Generate SEO meta for my last 10 published posts', 'abilityhub' ); ?>"><?php esc_html_e( 'Generate SEO meta for last 10 posts', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Create a workflow that generates alt text whenever an image is uploaded', 'abilityhub' ); ?>"><?php esc_html_e( 'Auto alt-text on image upload', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'What AI abilities are available?', 'abilityhub' ); ?>"><?php esc_html_e( 'List available abilities', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Deactivate the abilityhub/auto-seo workflow', 'abilityhub' ); ?>"><?php esc_html_e( 'Deactivate auto-SEO workflow', 'abilityhub' ); ?></a></li>
		</ul>

		<div class="abilityhub-chat-sidebar__actions">
			<button id="abilityhub-chat-clear" class="button button-secondary button-small">
				<?php esc_html_e( 'Clear conversation', 'abilityhub' ); ?>
			</button>
		</div>
	</div>

	<div class="abilityhub-chat-main">
		<div id="abilityhub-chat-messages" class="abilityhub-chat-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat conversation', 'abilityhub' ); ?>">
			<div class="abilityhub-chat-message abilityhub-chat-message--assistant">
				<div class="abilityhub-chat-message__avatar">AI</div>
				<div class="abilityhub-chat-message__body">
					<?php echo wp_kses_post( sprintf(
						/* translators: %s: site name */
						__( 'Hello! I\'m <strong>AbilityOperator</strong>, your AI Site Operator for %s. I can help you run abilities, create workflows, and batch-process your content. What would you like to do?', 'abilityhub' ),
						'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
					) ); ?>
				</div>
			</div>
		</div>

		<div id="abilityhub-chat-typing" class="abilityhub-chat-typing" hidden>
			<span class="abilityhub-chat-typing__dot"></span>
			<span class="abilityhub-chat-typing__dot"></span>
			<span class="abilityhub-chat-typing__dot"></span>
		</div>

		<form id="abilityhub-chat-form" class="abilityhub-chat-form" novalidate>
			<label for="abilityhub-chat-input" class="screen-reader-text">
				<?php esc_html_e( 'Your message', 'abilityhub' ); ?>
			</label>
			<textarea
				id="abilityhub-chat-input"
				class="abilityhub-chat-form__input"
				rows="2"
				placeholder="<?php esc_attr_e( 'Ask me anything about your site…', 'abilityhub' ); ?>"
				required
			></textarea>
			<button type="submit" id="abilityhub-chat-send" class="button button-primary abilityhub-chat-form__send">
				<?php esc_html_e( 'Send', 'abilityhub' ); ?>
			</button>
		</form>
	</div>

</div>
