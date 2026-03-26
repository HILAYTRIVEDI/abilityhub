# AbilityHub

**Contributors:** hilaytrivedi  
**Tags:** ai, abilities, mcp, woocommerce, wp-7-0, automation  
**Requires at least:** 7.0  
**Tested up to:** 7.0  
**Requires PHP:** 7.4  

An admin UI and workflow engine for the WordPress 7.0 Abilities API. It provides execution logging, human-in-the-loop approvals, and an MCP-compliant tool manifest.

---

## Description

AbilityHub acts as a management layer for the native WordPress 7.0 AI stack. While Core provides the registration and connection logic, this plugin adds the administrative tools needed to monitor and chain those capabilities.

---

## Technical Implementation

- **Abilities API:** Uses `wp_register_ability()` to expose 15 built-in tools for SEO, WooCommerce, and Developer documentation.
- **Unified Discovery:** The "Installed" tab uses `wp_get_abilities()` to list every registered ability on the site, regardless of the originating plugin.
- **Workflow Engine:** Chaining logic that triggers on standard WP hooks (`post_published`, etc.) with a Custom Post Type (`abilityhub_approval_queue`) for manual review before execution.
- **MCP Bridge:** A specialized endpoint that transforms `wp_get_abilities()` data into a protocol-compliant JSON manifest (v2024-11-05).

---

## Included Abilities

### Content & Media

- **SEO Metadata:** Generates titles and descriptions via post content.
- **Tone & Summary:** Content transformation tools (Professional, Casual, TL;DR).
- **Vision Alt-Text:** WCAG-compliant alt text generation via the Connectors API.

### WooCommerce (8.0+)

- **Product Descriptions:** Generates descriptions from attributes.
- **Review Management:** Drafts empathetic responses based on star ratings.

### Developer Tools

- **Pattern Generator:** Converts natural language to PHP block pattern registration code.
- **Error Explainer:** Interprets PHP errors and suggests fixes.
- **Hook Documenter:** Generates PHPDoc blocks for actions and filters.

---

## Key Modules

### Admin Interface

- **The Store:** A filterable UI to browse and test abilities via a JSON playground.
- **Execution Logs:** A custom database table (`{prefix}abilityhub_logs`) tracking status, duration (ms), and user ID. Includes a daily cron for auto-purging.
- **AI Operator:** A REST-backed chat panel (using `WP_AI_Client`) for executing abilities via natural language intents.

### Model Context Protocol (MCP)

The `abilityhub/mcp-capability-manifest` ability collects all registered site tools and maps their input/output schemas to the MCP spec. This allows external agents (Claude, Gemini, etc.) to use your WordPress site as a discoverable tool-provider.

---

## Security & Privacy

- **Provider Agnostic:** Uses the `wp_ai_client_prompt()` wrapper; no API keys are stored locally in this plugin.
- **Permissions:** All execution endpoints are protected by `edit_posts` or `manage_options` capability checks.
- **Data Integrity:** All database queries use `$wpdb->prepare()`, and inputs are sanitized via the Abilities API schema validation.

---

## Installation

1. Ensure WordPress 7.0+ is installed.
2. Configure a provider in **Settings > AI** (native Connectors API).
3. Upload and activate AbilityHub.
4. Access the dashboard via the AbilityHub sidebar menu.
