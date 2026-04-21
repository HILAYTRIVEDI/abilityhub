## What problem does this address?

Site administrators have no control over which AI abilities are active on their WordPress installation. Once AbilityHub is activated, all 15 abilities are registered and exposed via the REST API and the WordPress Abilities API simultaneously. This creates two pain points:

1. **Unnecessary exposure** — abilities that are irrelevant to the site (e.g. WooCommerce abilities on a site without WooCommerce, or developer-only abilities on a content team's install) are still publicly registered and discoverable via `/wp-abilities/v1/abilities` and the MCP manifest.
2. **No granular control** — there is no way to turn off a specific ability without deactivating the entire plugin or writing custom code to unhook it.

## What is your proposed solution?

Add a per-ability enable/disable toggle that admins can control from the **Installed** tab in the AbilityHub admin dashboard.

**Behaviour:**

- All 15 abilities are **enabled by default** — no change to the out-of-the-box experience.
- An admin with the `manage_options` capability can disable any AbilityHub ability using the **Disable** button next to it in the Installed tab.
- A disabled ability is **not registered** with the WordPress Abilities API — it will not appear in `wp_get_abilities()`, the REST discovery endpoint, or the MCP manifest.
- Disabled abilities remain **visible in the Installed tab** (with a red "Disabled" badge and a dimmed row) so admins can re-enable them at any time with a single click.
- The enabled/disabled state is stored in the `abilityhub_disabled_abilities` WordPress option (an array of ability slugs) and persists across page loads and plugin updates.
- Third-party abilities registered by other plugins are listed but their toggle is not exposed — AbilityHub only controls its own namespace (`abilityhub/*`).

**UI summary:**

- Installed tab gains a **Status** column showing a green "Enabled" or red "Disabled" pill per ability.
- Installed tab gains an **Enable / Disable** button in the Actions column, visible only to `manage_options` users for `abilityhub/` abilities.
- Toggling updates the row live via AJAX — no page reload required.
- A summary note at the top of the table shows how many abilities are currently disabled.
