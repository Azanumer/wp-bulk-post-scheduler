<?php
/**
 * Uninstall WP Bulk Post Scheduler.
 *
 * The plugin stores no options and no custom tables, so there is nothing
 * to clean up. Scheduled posts keep their future dates — uninstalling
 * never un-schedules content.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
