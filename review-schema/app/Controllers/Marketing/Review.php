<?php

namespace Rtrs\Controllers\Marketing; 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Review {

    public static function init() {
        $current      = time();
		$black_friday = mktime(0, 0, 0, 11, 17, 2022) <= $current && $current <= mktime(0, 0, 0, 12, 02, 2022);
		if (! $black_friday) {
            add_action( 'admin_init', [__CLASS__, 'rtrs_check_installation_time'] );
            add_action( 'admin_init', [__CLASS__, 'rtrs_spare_me'], 5 );
        }
    }

    //check if review notice should be shown or not
    public static function rtrs_check_installation_time() {
	    if ( isset($GLOBALS['rtrs_notice']) ) {
		    return;
	    }
        // Added Lines Start 
        $nobug = get_option( 'rtrs_spare_me', "0"); 

        if ($nobug == "1" || $nobug == "3") {
            return; 
        }

        $install_date = get_option( 'rtrs_plugin_activation_time' );

        // No activation time recorded yet — never show immediately after install.
        if ( ! $install_date ) {
            return;
        }

        $past_date   = strtotime( '-15 days' );
        $remind_time = get_option( 'rtrs_remind_me' );
        $now         = strtotime( 'now' );

        if ( $remind_time ) {
            // User clicked "Remind Me Later" — show again 15 days after that click.
            $remind_due = strtotime( '+15 days', $remind_time );
            if ( $now >= $remind_due ) {
                add_action( 'admin_notices', [__CLASS__, 'rtrs_display_admin_notice']);
            }
        } else if ( ( $past_date >= $install_date ) && $nobug !== "2" ) {
            // First-time prompt — only after 15 days since activation.
            add_action( 'admin_notices', [__CLASS__, 'rtrs_display_admin_notice']);
        }
    }

    /**
     * Display Admin Notice, asking for a review
     **/
    public static function rtrs_display_admin_notice() {
        // wordpress global variable
        global $pagenow;

        $exclude = [ 'themes.php', 'users.php', 'tools.php', 'options-general.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php', 'edit-comments.php', 'upload.php', 'media-new.php', 'admin.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php' ];

        if ( ! in_array( $pagenow, $exclude ) ) {

            $args         = [ '_wpnonce' => wp_create_nonce( 'rtrs_notice_nonce' ) ];

            $dont_disturb = esc_url( add_query_arg( $args + ['rtrs_spare_me' => '1'], self::rtrs_current_admin_url() ) );
            $remind_me    = esc_url( add_query_arg( $args + ['rtrs_remind_me' => '1'], self::rtrs_current_admin_url() ) );
            $rated        = esc_url( add_query_arg( $args + ['rtrs_rated' => '1'], self::rtrs_current_admin_url() ) );
            $reviewurl    = esc_url( 'https://wordpress.org/support/plugin/review-schema/reviews/' );

            $heading      = esc_html__( 'Enjoying SchemaEngine AI?', 'review-schema' );
            $message      = esc_html__( 'Thank you for choosing SchemaEngine AI. If you have found our plugin useful and makes you smile, please consider giving us a 5-star rating on WordPress.org. It will help us to grow.', 'review-schema' );
            $btn_rate     = esc_html__( '⭐ Yes, You Deserve It!', 'review-schema' );
            $btn_rated    = esc_html__( '😀 Already Rated!', 'review-schema' );
            $btn_remind   = esc_html__( '🔔 Remind Me Later', 'review-schema' );
            $btn_dismiss  = esc_html__( '😐 No Thanks', 'review-schema' );

            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All values are pre-escaped above via esc_html__() / esc_url() before being interpolated.
            printf(
                '<div class="notice rtrs-review-notice rtrs-review-notice--extended">
                <a href="%7$s" class="rtrs-review-notice_dismiss" aria-label="' . esc_attr__( 'Dismiss this notice', 'review-schema' ) . '"><span class="dashicons dashicons-no-alt"></span></a>
                <div class="rtrs-review-notice_content">
                    <h3>%1$s</h3>
                    <p>%2$s</p>
                    <div class="rtrs-review-notice_actions">
                        <a href="%3$s" class="rtrs-review-button rtrs-review-button-action rtrs-review-button--cta" target="_blank"><span>%4$s</span></a>
                        <a href="%5$s" class="rtrs-review-button rtrs-review-button--cta rtrs-review-button--outline"><span>%6$s</span></a>
                        <a href="%7$s" class="rtrs-review-button rtrs-review-button--cta rtrs-review-button--outline"><span>%8$s</span></a>
                    </div>
                </div>
            </div>',
                $heading,
                $message,
                $reviewurl,
                $btn_rate,
                $rated,
                $btn_rated,
                $remind_me,
                $btn_remind,
                $dont_disturb,
                $btn_dismiss
            );
            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

            echo '<style> 
            .rtrs-review-button--cta {
                --e-button-context-color: #5d3dfd;
                --e-button-context-color-dark: #5d3dfd;
                --e-button-context-tint: rgb(75 47 157/4%);
                --e-focus-color: rgb(75 47 157/40%);
            } 
            .rtrs-review-notice {
                position: relative;
                margin: 5px 20px 5px 2px;
                border: 1px solid #ccd0d4;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.15);
                font-family: Roboto, Arial, Helvetica, Verdana, sans-serif;
                border-inline-start-width: 4px;
            }
            .rtrs-review-notice.notice {
                padding: 0;
            }
            .rtrs-review-notice:before {
                position: absolute;
                top: -1px;
                bottom: -1px;
                left: -4px;
                display: block;
                width: 4px;
                background: -webkit-linear-gradient(bottom, #5d3dfd 0%, #6939c6 100%);
                background: linear-gradient(0deg, #5d3dfd 0%, #6939c6 100%);
                content: "";
            } 
            .rtrs-review-notice_content {
                padding: 20px;
            }
            .rtrs-review-notice_dismiss {
                position: absolute;
                top: 10px;
                right: 10px;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #787c82;
                text-decoration: none !important;
            }
            .rtrs-review-notice_dismiss:hover,
            .rtrs-review-notice_dismiss:focus {
                color: #d72b3f;
                box-shadow: none;
                outline: none;
            }
            .rtrs-review-notice_dismiss .dashicons {
                width: 20px;
                height: 20px;
                font-size: 20px;
                line-height: 20px;
            }
            .rtrs-review-notice_actions > * + * {
                margin-inline-start: 8px;
                -webkit-margin-start: 8px;
                -moz-margin-start: 8px;
            } 
            .rtrs-review-notice p {
                margin: 0;
                padding: 0;
                line-height: 1.5;
            }
            p + .rtrs-review-notice_actions {
                margin-top: 1rem;
            }
            .rtrs-review-notice h3 {
                margin: 0;
                font-size: 1.0625rem;
                line-height: 1.2;
            }
            .rtrs-review-notice h3 + p {
                margin-top: 8px;
            } 
            .rtrs-review-button {
                display: inline-block;
                padding: 0.4375rem 0.75rem;
                border: 0;
                border-radius: 3px;;
                background: var(--e-button-context-color);
                color: #fff;
                vertical-align: middle;
                text-align: center;
                text-decoration: none !important;
                white-space: nowrap; 
            }
            .rtrs-review-button-action{
                color: #fff !important;
            }
            .rtrs-review-button:active {
                background: var(--e-button-context-color-dark);
                color: #fff;
                text-decoration: none;
            }
            .rtrs-review-button:focus {
                outline: 0;
                background: var(--e-button-context-color-dark);
                box-shadow: 0 0 0 2px var(--e-focus-color);
                color: #fff;
                text-decoration: none;
            }
            .rtrs-review-button:hover {
                background: var(--e-button-context-color-dark);
                color: #fff;
                text-decoration: none;
            } 
            .rtrs-review-button.focus {
                outline: 0;
                box-shadow: 0 0 0 2px var(--e-focus-color);
            } 
            .rtrs-review-button--error {
                --e-button-context-color: #d72b3f;
                --e-button-context-color-dark: #ae2131;
                --e-button-context-tint: rgba(215,43,63,0.04);
                --e-focus-color: rgba(215,43,63,0.4);
            }
            .rtrs-review-button.rtrs-review-button--outline {
                border: 1px solid;
                background: 0 0;
                color: var(--e-button-context-color);
            }
            .rtrs-review-button.rtrs-review-button--outline:focus {
                background: var(--e-button-context-tint);
                color: var(--e-button-context-color-dark);
            }
            .rtrs-review-button.rtrs-review-button--outline:hover {
                background: var(--e-button-context-tint);
                color: var(--e-button-context-color-dark);
            } 
            </style>';
        }
    }

    // remove the notice for the user if review already done or if the user does not want to
    public static function rtrs_spare_me() {
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value verified via wp_verify_nonce() immediately after.
        if ( ! isset( $_REQUEST['_wpnonce'] ) || !wp_verify_nonce( $_REQUEST['_wpnonce'], 'rtrs_notice_nonce' ) ) {
			return;
		}

        if ( isset( $_GET['rtrs_spare_me'] ) && ! empty( $_GET['rtrs_spare_me'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only flag query parameter; only existence checked.
            $spare_me = $_GET['rtrs_spare_me'];
            if ( 1 == $spare_me ) {
                update_option( 'rtrs_spare_me', "1" );
            }
        }

        if ( isset( $_GET['rtrs_remind_me'] ) && ! empty( $_GET['rtrs_remind_me'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only flag query parameter; only existence checked.
            $remind_me = $_GET['rtrs_remind_me'];
            if ( 1 == $remind_me ) {
                $get_activation_time = strtotime( "now" );
                update_option( 'rtrs_remind_me', $get_activation_time );
                update_option( 'rtrs_spare_me', "2" );
            }
        }

        if ( isset( $_GET['rtrs_rated'] ) && ! empty( $_GET['rtrs_rated'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only flag query parameter; only existence checked.
            $rtrs_rated = $_GET['rtrs_rated'];
            if ( 1 == $rtrs_rated ) {
                update_option( 'rtrs_rated', 'yes' );
                update_option( 'rtrs_spare_me', "3" );
            }
        }
    }

    protected static function rtrs_current_admin_url() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );

        if ( ! $uri ) {
            return '';
        }
        return remove_query_arg( [ '_wpnonce', '_wc_notice_nonce', 'wc_db_update', 'wc_db_update_nonce', 'wc-hide-notice', 'rtrs_spare_me', 'rtrs_remind_me', 'rtrs_rated' ], admin_url( $uri ) );
    }
}  