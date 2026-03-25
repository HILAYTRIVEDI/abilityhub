# AbilityHub

**Contributors:** hilaytrivedi
**Tags:** ai, abilities, mcp, woocommerce, content, seo, block-editor, workflows, ai-operator
**Requires at least:** 7.0
**Tested up to:** 7.0
**Requires PHP:** 7.4
**Stable tag:** 1.0.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

The marketplace, hub, and orchestration platform for WordPress AI abilities — built natively on the WordPress 7.0 Abilities API with MCP server support, workflow automation, and a conversational AI Operator.

---

## Description

**AbilityHub** is the first marketplace and orchestration platform for WordPress AI abilities. Built natively on the WordPress 7.0 Abilities API, it ships 15 production-ready abilities across content, WooCommerce, developer tooling, media, and site management — plus a full workflow engine, AI chat operator, and built-in MCP server.

---

### What are WordPress Abilities?

The WordPress 7.0 Abilities API introduces a standardised way to register, discover, and execute AI-powered capabilities on any WordPress site. Think of abilities like WordPress blocks, but for AI actions:

- Any plugin registers abilities using `wp_register_ability()` on the `wp_abilities_api_init` hook
- Every registered ability is automatically available via the REST API at `/wp-json/wp-abilities/v1/abilities/{name}/execute`
- Abilities work with any AI provider configured via the WordPress Connectors API (OpenAI, Anthropic, Gemini, and more)
- The entire site becomes a discoverable AI tool server via the MCP Capability Manifest

---

### 15 Production-Ready Abilities

**Content**

- **Generate Meta Description** — Produces an SEO-optimised title (max 60 chars) and meta description (155 chars) from any post content
- **Rewrite Tone** — Rewrites content in five tones: professional, casual, friendly, authoritative, or humorous
- **Summarise Post** — Generates a concise summary plus a one-sentence TL;DR
- **Suggest Internal Links** — AI-powered internal linking suggestions pulled from your existing posts
- **Translate Block** — Translates block content into any BCP-47 language code

**WooCommerce** *(Requires WooCommerce 8.0+)*

- **Generate Product Description** — Short, long, and meta descriptions generated from product attributes
- **Write Review Response** — Professional, empathetic responses calibrated to the review's star rating
- **Generate Upsell Copy** — Compelling cross-sell and upsell copy for related products
- **Moderate Comment** — AI verdict (approve / flag / spam) with a confidence score

**Developer**

- **Generate Block Pattern** — Complete block pattern PHP registration code and block markup generated from a plain-English description
- **Explain PHP Error** — Plain-language error explanation, fix suggestion, and working code example
- **Write WP Hook Docs** — Full PHPDoc docblock and usage example for any WordPress action or filter

**Media**

- **Generate Alt Text** — WCAG-compliant alt text and optional caption via AI vision analysis
- **Suggest Image Filename** — SEO-friendly, descriptive filename suggestions for uploaded images

**Site**

- **MCP Capability Manifest** — Collects every ability registered on the site and returns a fully-formed MCP (Model Context Protocol) tool manifest, turning the WordPress site into a discoverable MCP server

---

### Admin Dashboard

AbilityHub adds a dedicated admin menu with seven sections:

**Dashboard**
Real-time stats (total abilities installed, executions today, executions this week, most-used ability), AI provider status, the WordPress 7.0 Abilities API explainer card, and a Quick Execute panel for testing any ability with custom JSON input directly from the dashboard.

**Ability Store**
A filterable card grid of all 15 built-in abilities. Each card shows the ability's label, description, and category. A "Try it" modal lets you execute any ability with pre-filled example JSON and see the live response.

**Installed**
A universal ability explorer that lists every ability registered on the site — regardless of which plugin registered it. Displays ability name, label, category, and its full REST endpoint. AbilityHub is a hub, not a silo.

**Execution Logs**
Paginated audit trail of every ability execution with columns for ID, ability name, status (success / error), duration in milliseconds, user ID, and timestamp. Supports filtering by ability, status, and date range. Full CSV export available.

**Workflows**
Visual interface for the built-in workflow automation engine. Includes an approval queue showing pending workflows awaiting human review before execution, with approve and reject actions.

**AI Operator**
A conversational chat panel for natural-language site operations. Describe what you want done in plain English and the AI Operator parses the intent, executes the relevant abilities, and reports back — with per-user conversation history.

**Settings**
Configure logging on/off, log retention period, view the AI provider status, see REST and MCP endpoint details, and connect an AbilityHub Registry API key (coming soon).

---

### Workflow Automation Engine

AbilityHub includes a complete workflow engine for chaining abilities together and triggering them automatically on WordPress events.

- **Trigger events**: `post_published`, `image_uploaded`, `comment_submitted`
- **Ability chaining**: run multiple abilities in sequence, passing output from one into the next
- **Approval guardrail**: results are held in an approval queue (custom post type) for human review before any action is taken (enabled by default)
- **Deactivation**: each workflow can be deactivated individually from the Workflows screen

**Built-in demo workflows:**

- `abilityhub/auto-seo` — triggers on `post_published`; runs Generate Meta Description then Suggest Internal Links
- `abilityhub/auto-alt-text` — triggers on `image_uploaded`; runs Generate Alt Text then Suggest Image Filename

---

### AI Site Operator (Chat)

A natural-language interface for AI-powered site operations, powered by the WordPress 7.0 AI Client.

- Maintains per-user conversation history (stored in the database, scoped to the current user)
- Scans site context automatically to ground the AI's responses
- Parses structured intents from AI responses and executes them via ability calls
- Supports batch operations with async tracking via a dedicated batch-status endpoint
- Full conversation history can be cleared at any time

**REST endpoints:**

- `POST /wp-json/abilityhub/v1/chat` — send a message
- `GET /wp-json/abilityhub/v1/chat/history` — retrieve conversation history
- `DELETE /wp-json/abilityhub/v1/chat/history` — clear conversation history
- `GET /wp-json/abilityhub/v1/batch/{id}` — poll a batch job's status

---

### MCP Server Integration

The `abilityhub/mcp-capability-manifest` ability turns any WordPress site running AbilityHub into a fully-discoverable MCP (Model Context Protocol) server.

- Calls `wp_get_abilities()` to collect every registered ability on the site
- Transforms them into the MCP tool manifest format (protocol v2024-11-05)
- Includes server info (site name, version, URL), a tools array with `inputSchema` for each ability, and total ability count
- Any MCP-compatible AI agent (Claude, GPT-4o, Gemini, etc.) can discover and call WordPress abilities as first-class tools

---

### Key Features

- **Provider-agnostic** — Works with any AI provider configured in WordPress 7.0 (OpenAI, Anthropic, Gemini, and more); no hard-coded API key required
- **Universal ability explorer** — The Installed tab shows every ability from every plugin, not just AbilityHub's own
- **Quick Execute panel** — Test any ability with custom JSON input directly from the admin dashboard
- **Execution logging** — Full audit trail with per-execution duration tracking, status, and CSV export
- **Workflow engine** — Chain abilities on WordPress events with approval guardrails
- **AI Operator chat** — Natural-language site operations with per-user conversation history
- **MCP server** — Every WordPress site with AbilityHub becomes a discoverable MCP tool server
- **Developer-first** — Every ability exposes a documented REST endpoint with a JSON input/output schema
- **Secure by default** — Nonce-protected AJAX, capability checks (`edit_posts` / `manage_options`), sanitized inputs, escaped outputs, and `$wpdb->prepare()` on all queries
- **Automatic cleanup** — Daily cron job purges logs older than the configured retention period (default: 30 days)

---

### Requirements

- WordPress 7.0 or higher (Abilities API and WP AI Client)
- PHP 7.4 or higher
- An AI provider configured via the WordPress Connectors API
- WooCommerce 8.0+ (optional — required only for WooCommerce abilities)

---

## Installation

1. Upload the `abilityhub` folder to `/wp-content/plugins/`
2. Activate the plugin via the **Plugins** menu in WordPress
3. Navigate to **AbilityHub** in the admin sidebar
4. Confirm an AI provider is configured under **Settings > AI** (WordPress 7.0 Connectors API)
5. Open the **Ability Store** tab and click **Try it** next to any ability to run your first execution

---

## Frequently Asked Questions

### Do I need to enter an API key?

No. AbilityHub uses the WordPress 7.0 AI Client (`wp_ai_client_prompt()`), which delegates to whichever provider is configured in the WordPress Connectors API. Set up your provider once there and all abilities use it automatically — no per-plugin key management needed.

### Will this work on WordPress 6.x?

No. The Abilities API is a WordPress 7.0 feature. AbilityHub guards against this with `function_exists('wp_register_ability')` checks and returns a clear `WP_Error` on older versions rather than breaking silently.

### Can other plugins register abilities that AbilityHub displays?

Yes — that is by design. The **Installed** tab lists every ability registered on the site via `wp_get_abilities()`, regardless of which plugin registered it. AbilityHub is a hub, not a walled garden.

### What is the MCP Capability Manifest?

The `abilityhub/mcp-capability-manifest` ability calls `wp_get_abilities()`, transforms the result into an MCP-compatible tool manifest (protocol v2024-11-05), and returns it as JSON. Any AI agent that speaks MCP can then discover and call your WordPress site's abilities as tools — no custom integration required.

### Are WooCommerce abilities safe to use without WooCommerce?

Yes. Every WooCommerce ability runs `class_exists('WooCommerce')` before executing and returns a descriptive `WP_Error` if WooCommerce is not active. No fatal errors, no silent failures.

### What does the approval queue do?

When a workflow runs, its results are held in a custom post type (`abilityhub_approval_queue`) until a user with `manage_options` approves them from the **Workflows** screen. This prevents automated AI actions from taking effect without human review. Approval is required by default and can be observed per workflow.

### Where are execution logs stored?

Logs are written to a dedicated `{prefix}abilityhub_logs` database table created on activation. Each row records the ability name, status (success/error), execution duration in milliseconds, user ID, and timestamp. Logs are purged automatically after the configured retention period (default: 30 days) via a daily cron job.

### Can I turn off logging?

Yes. Go to **AbilityHub > Settings** and toggle the "Enable Logging" option. The database table is retained; only new executions stop being written.

### How does the AI Operator chat work?

The AI Operator is a REST-backed conversational interface. It scans the site context, builds a system prompt, sends your message to the configured AI provider, parses structured intents from the response, and executes the corresponding abilities — all within one round-trip. Conversation history is stored per user and can be cleared at any time.

### What data is deleted when I uninstall the plugin?

Running the WordPress uninstaller (delete from the Plugins screen) drops the `{prefix}abilityhub_logs` table, removes all plugin options, and cleans up workflow and batch custom post data. No data is left behind.

---

## Screenshots

1. Dashboard — live stats, Quick Execute panel, AI provider status, and WP 7.0 API explainer
2. Ability Store — all 15 abilities in a filterable card grid with "Try it" modals
3. Try it modal — execute any ability with example JSON and see the live response
4. Installed — universal ability explorer listing all site abilities with their REST endpoints
5. Execution Logs — paginated audit trail with filters and CSV export
6. Workflows — workflow list with approval queue and pending badge
7. AI Operator — conversational chat panel with per-user history
8. Settings — logging configuration, retention period, and endpoint reference

---

## Changelog

### 1.0.0

- Initial release
- 15 production-ready abilities across Content, WooCommerce, Developer, Media, and Site categories
- Admin dashboard with live stats and Quick Execute panel
- Ability Store with filterable grid and "Try it" modals
- Universal ability explorer (Installed tab) showing all site abilities
- Execution logging with CSV export and configurable retention
- Workflow automation engine with event triggers and approval queue
- AI Site Operator chat with per-user conversation history and batch job support
- MCP Capability Manifest — turns any WordPress site into a discoverable MCP server
- Provider-agnostic via the WordPress 7.0 AI Client
- Daily cron cleanup for log retention

---

## Upgrade Notice

### 1.0.0

Initial release. Requires WordPress 7.0+ and an AI provider configured via the WordPress Connectors API.
