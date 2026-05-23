<?php
namespace Rtrs\Modules\Review\Admin\Meta;

use Rtrs\Helpers\Functions;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AddReviewMetaBox {
	/**
	 * SingleTon
	 */
	use SingletonTrait;

	/**
	 * Construct
	 */
	public function __construct() {
		// actions.
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
		add_action( 'admin_head', [ $this, 'add_meta_boxes' ] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( Functions::is_edit_page() ) {
			add_action( 'admin_footer', [ $this, 'pro_alert_html' ] );
		}
		add_action( 'pre_post_update', [ $this, 'before_update_post' ] );
		add_action( 'before_delete_post', [ $this, 'before_delete_post' ], 10, 2 );
		// rtrs post type.
		add_filter( 'manage_edit-rtrs_columns', [ $this, 'rtrs_columns_title_arrange' ] );
		add_action( 'manage_rtrs_posts_custom_column', [ $this, 'rtrs_columns_data_arrange' ], 10, 2 );

		// rtrs affiliate post type.
		add_action( 'edit_form_after_title', [ $this, 'rtrs_sc_after_title' ] );
		add_filter( 'manage_edit-rtrs_affiliate_columns', [ $this, 'rtrs_affiliate_columns_title_arrange' ] );
		add_action( 'manage_rtrs_affiliate_posts_custom_column', [ $this, 'rtrs_affiliate_columns_data_arrange' ], 10, 2 );
		add_filter( 'preprocess_comment', [ $this, 'modify_comment_type' ] );
	}

	/**
	 * Add admin error notice.
	 *
	 * @param string $message Error message.
	 */
	private function add_admin_error( $message ) {
		set_transient( 'rtrs_admin_notice', $message, 30 ); // expire in 30 seconds.
	}

	/**
	 * Redirect back to the previous page safely.
	 *
	 * @return void
	 */
	private function redirect_back() {
		$redirect = wp_get_referer() ? wp_get_referer() : admin_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return void
	 */
	public function render_notices() {
		$message = get_transient( 'rtrs_admin_notice' );
		if ( $message ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			delete_transient( 'rtrs_admin_notice' );
		}
	}

	/**
	 * Comment type review
	 * when post any comment, add comment type as review.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public function modify_comment_type( $commentdata ) {
		$post_type = get_post_type( $commentdata['comment_post_ID'] );
		if ( Functions::isEnableReviewByPostType( $post_type ) ) {
			$commentdata['comment_type'] = 'review';
		}

		return $commentdata;
	}

	/**
	 * @param $columns
	 * @return array
	 */
	public function rtrs_columns_title_arrange( $columns ) {
		$shortcode = [
			'post_type' => esc_html__( 'Post Type', 'review-schema' ),
			'support'   => esc_html__( 'Review Support', 'review-schema' ),
		];

		$columns = array_slice( $columns, 0, 2, true ) + $shortcode + array_slice( $columns, 1, null, true );

		if ( isset( $columns['date'] ) ) {
			unset( $columns['date'] );
			$columns['rtrs_date'] = esc_html__( 'Date', 'review-schema' );
		}

		return $columns;
	}

	/**
	 * @param $column
	 * @return void
	 */
	public function rtrs_columns_data_arrange( $column ) {
		switch ( $column ) {
			case 'post_type':
				if ( $post_type = get_post_meta( get_the_ID(), 'rtrs_post_type', true ) ) {
					echo esc_html( $post_type );
				}
				break;
			case 'support':
				$support = get_post_meta( get_the_ID(), 'rtrs_support', true );
				echo $support ? esc_html__( 'Enabled', 'review-schema' ) : esc_html__( 'Disabled', 'review-schema' );
				break;
			case 'rtrs_date':
				echo esc_html( get_the_date() );
				break;
			default:
				break;
		}
	}

	/**
	 * @param $columns
	 * @return array
	 */
	public function rtrs_affiliate_columns_title_arrange( $columns ) {
		$shortcode = [
			'shortcode' => esc_html__( 'Shortcode', 'review-schema' ),
		];

		$columns = array_slice( $columns, 0, 2, true ) + $shortcode + array_slice( $columns, 1, null, true );

		if ( isset( $columns['date'] ) ) {
			unset( $columns['date'] );
			$columns['rtrs_date'] = esc_html__( 'Date', 'review-schema' );
		}

		return $columns;
	}

	/**
	 * @param $column
	 * @return void
	 */
	public function rtrs_affiliate_columns_data_arrange( $column ) {
		switch ( $column ) {
			case 'shortcode':
				printf(
					'<input type="text" onfocus="this.select();" readonly="readonly" value="%s" class="large-text code rt-code-sc">',
					esc_attr( '[rtrs-affiliate id="' . get_the_ID() . '" title="' . get_the_title() . '"]' )
				);
				break;
			case 'rtrs_date':
				echo esc_html( get_the_date() );
				break;
			default:
				break;
		}
	}

	/**
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'rtrs_meta',
			esc_html__( 'Review Settings', 'review-schema' ),
			[ $this, 'rtrs_meta_settings' ],
			rtrs()->getPostType(),
			'normal',
			'high'
		);

		add_meta_box(
			'rt_plugin_sc_pro_information',
			esc_html__( 'Documentation', 'review-schema' ),
			[ $this, 'rt_plugin_sc_pro_information' ],
			rtrs()->getPostType(),
			'side',
			'low'
		);

		add_meta_box(
			'rtrs_meta',
			esc_html__( 'Affiliate Shortcode Generator', 'review-schema' ),
			[ $this, 'rtrs_affiliate_settings' ],
			'rtrs_affiliate',
			'normal',
			'high'
		);
	}

	/**
	 * @param $post
	 * @return void
	 */
	public function rt_plugin_sc_pro_information( $post ) {
		$html = '';

		$html .= sprintf(
			'<div class="rt-document-box">
				<div class="rt-box-icon"><i class="dashicons dashicons-media-document"></i></div>
				<div class="rt-box-content">
					<h3 class="rt-box-title">%1$s</h3>
						<p>%2$s</p>
						<a href="https://schemaengineai.com/docs/docs/ai-settings/" target="_blank" class="rt-admin-btn">%1$s</a>
				</div>
			</div>',
			esc_html__( 'Documentation', 'review-schema' ),
			esc_html__( 'Get started by spending some time with the documentation we included step by step process with screenshots with video.', 'review-schema' )
		);

		$html .= '<div class="rt-document-box">
                        <div class="rt-box-icon"><i class="dashicons dashicons-sos"></i></div>
                        <div class="rt-box-content">
                            <h3 class="rt-box-title">' . esc_html__( 'Need Help?', 'review-schema' ) . '</h3>
                                <p>' . esc_html__( 'Stuck with something? Please create a', 'review-schema' ) . ' 
                    <a href="https://www.radiustheme.com/contact/">' . esc_html__( 'ticket here', 'review-schema' ) . '</a> ' . esc_html__( 'or post on ', 'review-schema' ) . '<a href="https://www.facebook.com/groups/234799147426640/">facebook group</a>. ' . esc_html__( 'For emergency case join our', 'review-schema' ) . ' <a href="https://www.radiustheme.com/">' . esc_html__( 'live chat', 'review-schema' ) . '</a>.</p>
                                <a href="https://www.radiustheme.com/contact/" target="_blank" class="rt-admin-btn">' . esc_html__( 'Get Support', 'review-schema' ) . '</a>
                        </div>
                    </div>';

		$html .= '<div class="rt-document-box">
                <div class="rt-box-icon"><i class="dashicons dashicons-smiley"></i></div>
                <div class="rt-box-content">
                    <h3 class="rt-box-title">Happy Our Work?</h3>
                    <p>Thank you for choosing SchemaEngine AI. If you have found our plugin useful and makes you smile, please consider giving us a 5-star rating on WordPress.org. It will help us to grow.</p>
                    <a target="_blank" href="https://wordpress.org/support/plugin/review-schema/reviews/" class="rt-admin-btn">Yes, You Deserve It</a>
                </div>
            </div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;
	}

	/**
	 * @return void
	 */
	public function pro_alert_html() {
		$html = '';
		if ( ! function_exists( 'rtrsp' ) ) {
			$html .= '<div class="rt-document-box rt-alert rtrs-pro-alert">
                    <div class="rt-box-icon"><i class="dashicons dashicons-lock"></i></div>
                    <div class="rt-box-content">
                        <h3 class="rt-box-title">' . esc_html__( 'Pro field alert!', 'review-schema' ) . '</h3>
                        <p><span></span>' . esc_html__( 'Sorry! this is a pro field. To use this field, you need to use pro plugin.', 'review-schema' ) . '</p>
                        <a href="https://www.radiustheme.com/downloads/wordpress-review-structure-data-schema-plugin/?utm_source=WordPress&utm_medium=reviewschema&utm_campaign=pro_click" target="_blank" class="rt-admin-btn">' . esc_html__( 'Upgrade to pro', 'review-schema' ) . '</a>
                        <a href="#" target="_blank" class="rt-alert-close rtrs-pro-alert-close">x</a>
                    </div>
                </div>';
		}

		$html .= '<div class="rt-document-box rt-alert rtrs-post-type">
            <div class="rt-box-icon"><i class="dashicons dashicons-lock"></i></div>
            <div class="rt-box-content">
                <h3 class="rt-box-title">' . esc_html__( 'Already exist alert!', 'review-schema' ) . '</h3>
                <p>' . esc_html__( 'Sorry! this post type already exist, you need to choose new one.', 'review-schema' ) . '</p> 
                <a href="#" target="_blank" class="rt-alert-close rtrs-post-type-close">x</a>
            </div>
        </div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;
	}

	public function rtrs_sc_after_title( $post ) {
		if ( rtrs()->getPostTypeAffiliate() !== $post->post_type ) {
			return;
		}
		$html  = null;
		$html .= '<div class="postbox rt-after-title" style="margin-bottom: 0;"><div class="inside">';
		$html .= '<p><input type="text" onfocus="this.select();" readonly="readonly" value="[rtrs-affiliate id=&quot;' . esc_attr( $post->ID ) . '&quot; title=&quot;' . esc_attr( $post->post_title ) . '&quot;]" class="large-text code rt-code-sc">
        <input type="text" onfocus="this.select();" readonly="readonly" value="&#60;&#63;php echo do_shortcode( &#39;[rtrs-affiliate id=&quot;' . esc_attr( $post->ID ) . '&quot; title=&quot;' . esc_attr( $post->post_title ) . '&quot;]&#39; ); &#63;&#62;" class="large-text code rt-code-sc">
        </p>';
		$html .= '</div></div>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;
	}

	public function postType() {
		return apply_filters( 'rtrs_post_type', Functions::getPostTypes() );
	}

	public function rtrs_meta_settings( $post ) {
		$post = [
			'post' => $post,
		];
		wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() );

		// Auto select tab.
		$tab = get_post_meta( get_the_ID(), '_rtrs_sc_tab', true );
		if ( ! $tab || 'schema' == $tab ) {
			$tab = 'review';
		}
		$review_tab  = ( 'review' === $tab ) ? 'active' : '';
		$setting_tab = ( 'setting' === $tab ) ? 'active' : '';
		$style_tab   = ( 'style' === $tab ) ? 'active' : '';

		$html  = null;
		$html .= '<div id="rt-conditional-wrap" class="rtrs-tab-content" style="display: block;">';
		$html .= rtrs()->render( 'metas.sc.conditional', $post, true );
		$html .= '</div>';

		// meta tab
		$html .= '<div id="sc-tabs" class="rtrs-tab-container">';
		$html .= '<ul class="rtrs-tab-nav rt-back">
                <li class="review-tab ' . esc_attr( $review_tab ) . '"><a href="#sc-review"><i class="dashicons dashicons-star-filled"></i>' . esc_html__( 'Review', 'review-schema' ) . '</a></li> 
                <li class="' . esc_attr( $setting_tab ) . '"><a href="#sc-settings"><i class="dashicons dashicons-admin-tools"></i>' . esc_html__( 'Settings', 'review-schema' ) . '</a></li>
                <li class="' . esc_attr( $style_tab ) . '"><a href="#sc-style"><i class="dashicons dashicons-admin-customizer"></i>' . esc_html__( 'Style', 'review-schema' ) . '</a></li></ul>';

		$review_tab  = ( 'review' === $tab ) ? 'display: block' : '';
		$setting_tab = ( 'setting' === $tab ) ? 'display: block' : '';
		$style_tab   = ( 'style' === $tab ) ? 'display: block' : '';

		$html .= '<input type="hidden" id="_rtrs_sc_tab" name="_rtrs_sc_tab" value="' . esc_attr( $tab ) . '" />';

		$html .= '<div id="sc-review" class="rtrs-tab-content" style="' . esc_attr( $review_tab ) . '">';
		$html .= rtrs()->render( 'metas.sc.review', $post, true );
		$html .= '</div>';
		$html .= '<div id="sc-settings" class="rtrs-tab-content" style="' . esc_attr( $setting_tab ) . '">';
		$html .= rtrs()->render( 'metas.sc.settings', $post, true );
		$html .= '</div>';

		$html .= '<div id="sc-style" class="rtrs-tab-content" style="' . esc_attr( $style_tab ) . '">';
		$html .= rtrs()->render( 'metas.sc.style', $post, true );
		$html .= '</div>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;

		echo '</div>'; // wrap div
	}

	public function rtrs_affiliate_settings( $post ) {
		$post = [
			'post' => $post,
		];
		wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() );

		// auto select tab
		$tab = get_post_meta( get_the_ID(), '_rtrs_sc_tab', true );
		if ( ! $tab ) {
			$tab = 'affiliate';
		}

		$affiliate_tab = ( $tab == 'affiliate' ) ? 'active' : '';
		$style_tab     = ( $tab == 'style' ) ? 'active' : '';
		$html          = null;
		$html         .= '<div id="sc-tabs" class="rtrs-tab-container">';
		$html         .= '<ul class="rtrs-tab-nav">
                <li class="' . esc_attr( $affiliate_tab ) . '"><a href="#sc-affiliate"><i class="dashicons dashicons-megaphone"></i>' . esc_html__( 'Affiliate', 'review-schema' ) . '</a></li>
                <li class="' . esc_attr( $style_tab ) . '"><a href="#sc-style"><i class="dashicons dashicons-admin-customizer"></i>' . esc_html__( 'Style', 'review-schema' ) . '</a></li></ul>';
		$affiliate_tab = ( $tab == 'affiliate' ) ? 'display: block' : '';
		$style_tab     = ( $tab == 'style' ) ? 'display: block' : '';
		$html         .= '<input type="hidden" id="_rtrs_sc_tab" name="_rtrs_sc_tab" value="' . esc_attr( $tab ) . '" />';

		$html .= '<div id="sc-affiliate" class="rtrs-tab-content" style="' . esc_attr( $affiliate_tab ) . '">';
		$html .= rtrs()->render( 'metas.affiliate.affiliate', $post, true );
		$html .= '</div>';

		$html .= '<div id="sc-style" class="rtrs-tab-content" style="' . esc_attr( $style_tab ) . '">';
		$html .= rtrs()->render( 'metas.affiliate.style', $post, true );
		$html .= '</div>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;

		echo '</div>'; // wrap div
	}
	/**
	 * Check if post type already exists before save.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function before_update_post( $post_id ) {
		if ( rtrs()->getPostType() !== get_post_type( $post_id ) ) {
			return;
		}
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_type         = isset( $_POST['rtrs_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rtrs_post_type'] ) ) : '';
		$scPostIds         = get_posts(
			[
				'post_type'      => rtrs()->getPostType(),
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', 'draft' ],
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
					[
						'key'     => 'rtrs_post_type',
						'value'   => $post_type,
						'compare' => '=',
					],
				],
			]
		);
		$current_post_type = get_post_meta( $post_id, 'rtrs_post_type', true );
		if ( ! post_type_exists( $post_type ) ) {
			$this->add_admin_error( __( 'Please choose a valid post type.', 'review-schema' ) );
			return;
		}
		if ( ( $current_post_type !== $post_type ) && ! empty( $scPostIds ) ) {
			$this->add_admin_error( __( 'This post type already exists. Please choose a new one.', 'review-schema' ) );
			$this->redirect_back();
		}
	}

	/**
	 * @param $post_id
	 * @param $post
	 *
	 * @return void
	 */
	public function before_delete_post( $post_id, $post ) {
		if ( rtrs()->getPostType() == $post->post_type ) {
			Functions::removeGeneratorShortCodeCss( $post_id, 'review' );
		} elseif ( 'rtrs_affiliate' == $post->post_type ) {
			Functions::removeGeneratorShortCodeCss( $post_id, 'affiliate' );
		}
	}
}
