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
			<?php esc_html_e( 'I can manage your WordPress site, fetch real-time data from the web, run AI abilities, and automate workflows. I will not assist with illegal or harmful requests.', 'abilityhub' ); ?>
		</p>

		<h3><?php esc_html_e( 'Site management', 'abilityhub' ); ?></h3>
		<ul class="abilityhub-chat-examples">
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Create a new draft post titled "Getting Started with AI" with some introductory content', 'abilityhub' ); ?>"><?php esc_html_e( 'Create a draft post', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Show me all my draft posts', 'abilityhub' ); ?>"><?php esc_html_e( 'List draft posts', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Update my site tagline to "Powered by AI"', 'abilityhub' ); ?>"><?php esc_html_e( 'Update site tagline', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Change posts per page to 6', 'abilityhub' ); ?>"><?php esc_html_e( 'Change posts per page', 'abilityhub' ); ?></a></li>
		</ul>

		<h3><?php esc_html_e( 'Real-time data', 'abilityhub' ); ?></h3>
		<ul class="abilityhub-chat-examples">
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Fetch the top 5 trending AI news from TechCrunch and summarise them', 'abilityhub' ); ?>"><?php esc_html_e( 'Top AI news today', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Fetch the latest WordPress news and create a draft post summarising it', 'abilityhub' ); ?>"><?php esc_html_e( 'WP news → draft post', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Fetch content from https://wordpress.org/news/feed/ and show me the latest 5 headlines', 'abilityhub' ); ?>"><?php esc_html_e( 'Read an RSS feed', 'abilityhub' ); ?></a></li>
		</ul>

		<h3><?php esc_html_e( 'AI abilities', 'abilityhub' ); ?></h3>
		<ul class="abilityhub-chat-examples">
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Generate SEO meta for my last 10 published posts', 'abilityhub' ); ?>"><?php esc_html_e( 'Batch SEO meta for 10 posts', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'Create a workflow that generates alt text whenever an image is uploaded', 'abilityhub' ); ?>"><?php esc_html_e( 'Auto alt-text on image upload', 'abilityhub' ); ?></a></li>
			<li><a href="#" class="abilityhub-chat-example" data-prompt="<?php esc_attr_e( 'What AI abilities are available?', 'abilityhub' ); ?>"><?php esc_html_e( 'List available abilities', 'abilityhub' ); ?></a></li>
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
						__( 'Hello! I\'m <strong>AbilityOperator</strong>, your AI Site Operator for %s. I can manage your site, fetch real-time data from the web (news, RSS feeds, APIs), run AI abilities, and automate workflows — all from this chat. I don\'t have access to the internet for browsing, but I can fetch content from specific URLs and feeds you ask for. I won\'t assist with illegal or harmful requests. What would you like to do?', 'abilityhub' ),
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
