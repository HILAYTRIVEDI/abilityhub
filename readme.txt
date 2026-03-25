=== AbilityHub ===
Contributors: abilityhub
Tags: ai, abilities, wordpress-ai, mcp, woocommerce, content, seo, block-editor
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The marketplace for WordPress AI abilities. Browse, install, and build composable AI capabilities natively on the WordPress 7.0 Abilities API.

== Description ==

**AbilityHub** is the first marketplace and hub for WordPress AI abilities — composable, registered, and provider-agnostic AI capabilities built natively on the WordPress 7.0 Abilities API.

= What are WordPress Abilities? =

The WordPress 7.0 Abilities API introduces a standardised way to register, discover, and execute AI-powered capabilities on any WordPress site. Think of abilities like WordPress blocks, but for AI actions:

* Any plugin registers abilities using `wp_register_ability()` on the `wp_abilities_api_init` hook
* Every registered ability is automatically available via the REST API
* Abilities work with any WordPress AI provider (configured via the WP AI Client)
* The entire site becomes a discoverable AI tool server via the MCP Capability Manifest

= 15 Production-Ready Abilities =

**Content**
* Generate Meta Description — SEO title + 155-char meta description from any post content
* Rewrite Tone — Rewrite content as professional, casual, friendly, authoritative, or humorous
* Summarise Post — Concise summary + one-sentence TL;DR
* Suggest Internal Links — AI-powered internal linking suggestions from your existing posts
* Translate Block — Translate block content into any BCP-47 language

**WooCommerce**
* Generate Product Description — Short, long, and meta descriptions from product attributes
* Write Review Response — Professional, empathetic responses calibrated to star rating
* Generate Upsell Copy — Compelling cross-sell copy for related products
* Moderate Comment — AI verdict (approve/flag/spam) with confidence score

**Developer**
* Generate Block Pattern — Complete block pattern PHP + markup from a description
* Explain PHP Error — Plain-language explanation + fix suggestion + code example
* Write WP Hook Docs — Full PHPDoc docblock + usage example for any hook

**Media**
* Generate Alt Text — Accessible, WCAG-compliant alt text + caption via AI vision
* Suggest Image Filename — SEO-friendly, descriptive filename suggestions

**Site**
* MCP Capability Manifest — **The showstopper.** Returns a full MCP-compatible tool manifest of every ability registered on your site, making it a discoverable MCP server.

= Key Features =

* **Provider-agnostic** — Works with any AI provider configured in WordPress 7.0 (OpenAI, Anthropic, Gemini, etc.)
* **Universal ability explorer** — The Installed tab shows ALL abilities from ALL plugins on your site
* **Quick Execute panel** — Test any ability directly from the admin dashboard with example inputs
* **Execution logs** — Full audit trail with duration tracking and CSV export
* **MCP server** — Any WordPress site with AbilityHub becomes a fully-discoverable MCP tool server
* **Developer-first** — Every ability has a documented REST endpoint and JSON schema

= Requirements =

* WordPress 7.0 or higher (uses the Abilities API and WP AI Client)
* PHP 7.4 or higher
* An AI provider configured via the WordPress Connectors API
* WooCommerce 8.0+ (optional, for WooCommerce abilities)

== Installation ==

1. Upload the `abilityhub` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **AbilityHub** in the admin sidebar
4. Ensure an AI provider is configured via **Settings > AI** (WordPress 7.0 Connectors API)

== Frequently Asked Questions ==

= Do I need to configure an API key? =

No. AbilityHub uses the WordPress 7.0 AI Client (`AI_Client::prompt()`), which is provider-agnostic. Configure your AI provider once via the WordPress Connectors API and all abilities use it automatically.

= Will abilities work on WordPress 6.x? =

No. The Abilities API requires WordPress 7.0+. The plugin guards against this with `function_exists('wp_register_ability')` checks and will return meaningful errors on older versions.

= Can other plugins register abilities too? =

Yes — that's the whole point. The **Installed** tab in AbilityHub shows every ability registered on your site, regardless of which plugin registered it. AbilityHub is a hub, not a silo.

= What is the MCP Capability Manifest? =

The `abilityhub/mcp-capability-manifest` ability calls `wp_get_abilities()` to collect every registered ability on the site and transforms them into MCP (Model Context Protocol) tool definitions. This means any AI agent or LLM that speaks MCP can discover and call your WordPress abilities as tools.

= Are WooCommerce abilities safe to use without WooCommerce? =

Yes. Every WooCommerce ability checks `class_exists('WooCommerce')` before executing and returns a `WP_Error` with a clear message if WooCommerce is inactive.

== Screenshots ==

1. Dashboard — stats, Quick Execute panel, and WP 7.0 API explainer
2. Ability Store — all 15 abilities in a filterable card grid
3. Try it modal — execute any ability with example JSON input
4. Installed — universal ability explorer showing all site abilities
5. Execution Logs — paginated audit trail with CSV export
6. Settings — logging configuration and REST/MCP endpoint info

== Changelog ==

= 1.0.0 =
* Initial release
* 15 production-ready abilities across content, WooCommerce, developer, media, and site categories
* Admin dashboard with Quick Execute panel
* Execution logging with CSV export
* Universal ability explorer (Installed tab)
* MCP Capability Manifest ability

== Upgrade Notice ==

= 1.0.0 =
Initial release. Requires WordPress 7.0+.
