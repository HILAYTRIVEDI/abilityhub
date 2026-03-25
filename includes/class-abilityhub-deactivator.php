<?php
/**
 * Fired during plugin deactivation.
 *
 * @package AbilityHub
 */

defined( 'ABSPATH' ) || exit;

class AbilityHub_Deactivator {

    public static function deactivate(): void {
        // Nothing to do on deactivation. DB tables remain for data preservation.
    }
}
