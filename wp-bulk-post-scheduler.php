<?php
/**
 * Plugin Name: WP Bulk Post Scheduler
 * Plugin URI:  https://github.com/Azanumer/wp-bulk-post-scheduler
 * Description: Schedule a batch of draft posts in one go — pick the drafts, set a start time and an interval, and each post goes live in sequence. Perfect for drip-feeding a content queue.
 * Version:     1.0.0
 * Author:      Azan Umer
 * Author URI:  https://azanumer.cv
 * License:     MIT
 * Text Domain: wbps
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WBPS_VERSION', '1.0.0' );

/**
 * Register the Tools page.
 */
function wbps_register_menu() {
    add_management_page(
        __( 'Bulk Post Scheduler', 'wbps' ),
        __( 'Bulk Scheduler', 'wbps' ),
        'manage_options',
        'wbps-bulk-scheduler',
        'wbps_render_page'
    );
}
add_action( 'admin_menu', 'wbps_register_menu' );

/**
 * Get the public post types that can be scheduled.
 *
 * @return string[] Post type names.
 */
function wbps_schedulable_post_types() {
    $types = get_post_types( array( 'public' => true ), 'names' );
    unset( $types['attachment'] );
    return apply_filters( 'wbps_post_types', array_values( $types ) );
}

/**
 * Render the Tools page and handle scheduling submissions.
 */
function wbps_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to use this page.', 'wbps' ) );
    }

    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
    if ( ! in_array( $post_type, wbps_schedulable_post_types(), true ) ) {
        $post_type = 'post';
    }

    $notice = '';
    if ( isset( $_POST['wbps_schedule'] ) ) {
        check_admin_referer( 'wbps_schedule_action' );

        $ids = isset( $_POST['wbps_posts'] ) ? array_map( 'absint', (array) $_POST['wbps_posts'] ) : array();
        $raw_start = isset( $_POST['wbps_start'] ) ? sanitize_text_field( wp_unslash( $_POST['wbps_start'] ) ) : '';
        $interval  = isset( $_POST['wbps_interval'] ) ? absint( $_POST['wbps_interval'] ) : 0;
        $unit      = isset( $_POST['wbps_unit'] ) ? sanitize_key( wp_unslash( $_POST['wbps_unit'] ) ) : 'days';
        $unit      = in_array( $unit, array( 'hours', 'days' ), true ) ? $unit : 'days';

        $start_ts = strtotime( str_replace( 'T', ' ', $raw_start ) );

        if ( empty( $ids ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Select at least one draft to schedule.', 'wbps' ) . '</p></div>';
        } elseif ( $start_ts === false ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'Enter a valid start date and time.', 'wbps' ) . '</p></div>';
        } elseif ( $start_ts <= current_time( 'timestamp' ) ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'The start time must be in the future.', 'wbps' ) . '</p></div>';
        } elseif ( $interval < 1 ) {
            $notice = '<div class="notice notice-error"><p>' . esc_html__( 'The interval must be at least 1.', 'wbps' ) . '</p></div>';
        } else {
            $step      = ( 'hours' === $unit ) ? $interval * HOUR_IN_SECONDS : $interval * DAY_IN_SECONDS;
            $step      = apply_filters( 'wbps_interval_seconds', $step, $interval, $unit );
            $scheduled = 0;

            foreach ( $ids as $index => $post_id ) {
                $post = get_post( $post_id );
                if ( ! $post || 'draft' !== $post->post_status ) {
                    continue;
                }
                $date = gmdate( 'Y-m-d H:i:s', $start_ts + ( $index * $step ) );
                $result = wp_update_post(
                    array(
                        'ID'            => $post_id,
                        'post_date'     => $date,
                        'post_date_gmt' => get_gmt_from_date( $date ),
                        'post_status'   => 'future',
                    ),
                    true
                );
                if ( ! is_wp_error( $result ) ) {
                    $scheduled++;
                }
            }

            $notice = '<div class="notice notice-success"><p>' . sprintf(
                /* translators: %d: number of posts scheduled. */
                esc_html__( '%d post(s) scheduled.', 'wbps' ),
                $scheduled
            ) . '</p></div>';
        }
    }

    $drafts = get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => 'draft',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
        )
    );

    // Default start: tomorrow at 09:00 site time.
    $default_start = gmdate( 'Y-m-d\TH:i', current_time( 'timestamp' ) + DAY_IN_SECONDS );
    $default_start = substr_replace( $default_start, '09:00', 11 );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bulk Post Scheduler', 'wbps' ); ?></h1>
        <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice is built above with escaping. ?>
        <p><?php esc_html_e( 'Pick drafts below, set when the first one should go live and how far apart they should publish. The oldest draft goes first.', 'wbps' ); ?></p>

        <form method="get" action="">
            <input type="hidden" name="page" value="wbps-bulk-scheduler" />
            <label for="wbps-post-type"><?php esc_html_e( 'Post type:', 'wbps' ); ?></label>
            <select name="post_type" id="wbps-post-type" onchange="this.form.submit()">
                <?php foreach ( wbps_schedulable_post_types() as $type ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $post_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ( empty( $drafts ) ) : ?>
            <p><em><?php esc_html_e( 'No drafts found for this post type. Write some drafts first.', 'wbps' ); ?></em></p>
        <?php else : ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'wbps_schedule_action' ); ?>
                <p>
                    <label for="wbps-start"><?php esc_html_e( 'First post goes live at:', 'wbps' ); ?></label>
                    <input type="datetime-local" id="wbps-start" name="wbps_start" value="<?php echo esc_attr( $default_start ); ?>" required />
                    &nbsp;
                    <label for="wbps-interval"><?php esc_html_e( 'Then one every', 'wbps' ); ?></label>
                    <input type="number" id="wbps-interval" name="wbps_interval" value="1" min="1" style="width: 70px;" required />
                    <select name="wbps_unit">
                        <option value="days"><?php esc_html_e( 'day(s)', 'wbps' ); ?></option>
                        <option value="hours"><?php esc_html_e( 'hour(s)', 'wbps' ); ?></option>
                    </select>
                </p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="wbps-check-all" checked /></th>
                            <th><?php esc_html_e( 'Title', 'wbps' ); ?></th>
                            <th><?php esc_html_e( 'Drafted', 'wbps' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $drafts as $draft ) : ?>
                            <tr>
                                <td><input type="checkbox" name="wbps_posts[]" value="<?php echo esc_attr( $draft->ID ); ?>" class="wbps-check" checked /></td>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $draft->ID ) ); ?>"><?php echo esc_html( get_the_title( $draft->ID ) ); ?></a></td>
                                <td><?php echo esc_html( get_the_date( '', $draft->ID ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" name="wbps_schedule" class="button button-primary" value="<?php esc_attr_e( 'Schedule selected posts', 'wbps' ); ?>" />
                </p>
            </form>
            <script>
            document.getElementById('wbps-check-all').addEventListener('change', function () {
                document.querySelectorAll('.wbps-check').forEach(function (box) {
                    box.checked = document.getElementById('wbps-check-all').checked;
                });
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}
