<?php
/**
 * Review Fns
 */
namespace Rtrs\Modules\Review\Helpers;

use Rtrs\Helpers\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewFns
 *
 * Provides a set of static methods for handling review-related functionalities.
 *
 * @package SchemaEngine AI
 */
class ReviewFns {

	/**
	 * Calculate and return the average rating for a post.
	 *
	 * @param int  $id                Post ID.
	 * @param bool $is_frontend_view Optimized Query By the meta key, Whether the value is requested for frontend (uses stored meta if available).
	 *
	 * @return float|false Average rating or false if no rating exists.
	 *
	 * @since 1.0
	 */
	public static function getAvgRatings( $id, $is_frontend_view = false ) {
		if ( $is_frontend_view ) {
			$avg_rating = get_post_meta( $id, 'rtrs_avg_rating', true );
			if ( ! empty( $avg_rating ) ) {
				return $avg_rating;
			}
		}
		$comments = get_approved_comments( $id );

		if ( $comments ) {
			$i     = 0;
			$total = 0;
			foreach ( $comments as $comment ) {
				$rate = get_comment_meta( $comment->comment_ID, 'rating', true );
				if ( isset( $rate ) && '' !== $rate ) {
					$i++;
					$total += $rate;
				}
			}

			if ( 0 === $i ) {
				return false;
			} else {
				return round( $total / $i, 1 );
			}
		} else {
			return false;
		}
	}

	/**
	 *  Average rating by star
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getAvgRatingByStar( $id ) {
		$comments = get_approved_comments( $id );

		if ( $comments ) {
			$total = [
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 0,
			];
			foreach ( $comments as $comment ) {
				$rate = get_comment_meta( $comment->comment_ID, 'rating', true );
				if ( isset( $rate ) && '' !== $rate ) {
					if ( $rate >= 4.01 && $rate <= 5 ) {
						$total[5]++;
					} elseif ( $rate >= 3.01 && $rate <= 4 ) {
						$total[4]++;
					} elseif ( $rate >= 2.01 && $rate <= 3 ) {
						$total[3]++;
					} elseif ( $rate >= 1.01 && $rate <= 2 ) {
						$total[2]++;
					} elseif ( $rate >= 1 && $rate <= 1.99 ) {
						$total[1]++;
					}
				}
			}

			return $total;
			// if ( 0 === $i ) {
			// return false;
			// } else {
			// return round( $total / $i, 1 );
			// }
		} else {
			return false;
		}
	}

	/**
	 *  Get Total Rating
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getTotalRatings( $id ) {
		$args     = [
			'type'       => 'review',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary to count reviews; result is small per-post.
			'meta_query' => [
				[
					'key'     => 'rating',
					'compare' => 'EXISTS',
				],
			],
		];
		$comments = get_approved_comments( $id, $args );

		if ( $comments ) {
			return count( $comments );
		}
	}
	/**
	 *  Get Total Rating
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getTotalGDPRForbiddenReview( $id ) {
		$args     = [
			'type'       => 'review',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary to count GDPR-forbidden reviews; result is small per-post.
			'meta_query' => [
				[
					'key'     => 'rtrs_review_gdpr_consent',
					'value'   => 'hide',
					'compare' => '=',
				],
			],
		];
		$comments = get_approved_comments( $id, $args );

		if ( $comments ) {
			return count( $comments );
		}
	}

	/**
	 *  Get Best Rating
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getBestRating( $id ) {
		$args     = [
			'order'    => 'DSC',
			'number'   => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary to sort reviews by rating value.
			'meta_key' => 'rating',
			'orderby'  => 'meta_value_num',
		];
		$comments = get_approved_comments( $id, $args );
		$rating   = null;
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
			}
		}
		return $rating;
	}

	/**
	 *  Get Worst Rating
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getWorstRating( $id ) {
		$args     = [
			'order'    => 'ASC',
			'number'   => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary to sort reviews by rating value.
			'meta_key' => 'rating',
			'orderby'  => 'meta_value_num',
		];
		$comments = get_approved_comments( $id, $args );
		$rating   = null;
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
			}
		}
		return $rating;
	}

	/**
	 * Get native comment ratings for a post.
	 *
	 * Queries standard WordPress comments (excluding the plugin's 'review' type)
	 * that have a 'rating' comment meta. Used as fallback when the plugin's
	 * review feature is disabled.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array{total: int, average: float}|false Rating data or false if none found.
	 */
	public static function getNativeCommentRatings( $post_id ) {
		$comments = get_comments(
			[
				'post_id'      => $post_id,
				'status'       => 'approve',
				'type__not_in' => [ 'review' ],
				'meta_query'   => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => 'rating',
						'type'    => 'NUMERIC',
						'compare' => '>',
						'value'   => 0,
					],
				],
			]
		);

		if ( empty( $comments ) ) {
			return false;
		}

		$total = 0;
		$count = count( $comments );
		foreach ( $comments as $comment ) {
			$total += (float) get_comment_meta( $comment->comment_ID, 'rating', true );
		}

		return [
			'total'   => $count,
			'average' => round( $total / $count, 1 ),
		];
	}

	/**
	 *  Average ratings
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getCriteriaAvgRatings( $p_id ) {
		// get criteria rating
		$criteria_total_sum = $criteria_avg = $criteria_name_avg = [];

		$comments = get_approved_comments( $p_id );
		if ( $comments ) {
			$i = 0;
			// get total of each criteria by comments
			foreach ( $comments as $comment_key => $comment ) {
				$rating = get_comment_meta( $comment->comment_ID, 'rt_rating_criteria', true );
				if ( isset( $rating ) && '' !== $rating ) {
					if ( is_array( $rating ) && count( $rating ) ) {
						$i++;
					}
				}

				// calculate criteria
				if ( $rating ) {
					foreach ( $rating as $rate_key => $rate ) {
						if ( isset( $criteria_total_sum[ $rate_key ] ) ) {
							$criteria_total_sum[ $rate_key ] += $rate;
						} else {
							$criteria_total_sum[ $rate_key ] = $rate;
						}
					}
				}
			}

			// get avg of criteria
			foreach ( $criteria_total_sum as $c_key => $value ) {
				$criteria_avg[] = round( $value / $i, 1 );
			}

			// adjust avg rating with criteria name
			if ( $multi_criteria = Functions::getCriteriaByPostType() ) {
				foreach ( $multi_criteria as $criteria_key => $value ) :
					$criteria_name_avg[ $criteria_key ]['title'] = $value;
					if ( isset( $criteria_avg[ $criteria_key ] ) ) {
						$criteria_name_avg[ $criteria_key ]['avg'] = $criteria_avg[ $criteria_key ];
					} else {
						$criteria_name_avg[ $criteria_key ]['avg'] = 5;
					}
				endforeach;
			}

			return $criteria_name_avg;

		} else {
			return [];
		}
	}

	/**
	 *  Get total recommedation
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function getTotalRecommendation( $id ) {
		$comments = get_approved_comments( $id );

		if ( $comments ) {
			$i = 0;
			foreach ( $comments as $comment ) {
				$rate = get_comment_meta( $comment->comment_ID, 'rt_recommended', true );
				if ( isset( $rate ) && 1 == $rate ) {
					$i++;
				}
			}
			return $i;

		} else {
			return 0;
		}
	}

	/**
	 *  Comment list
	 *
	 * @package SchemaEngine AI
	 * @since 1.0
	 */
	public static function comment_list( $comment, $args, $depth ) {
		extract( $args, EXTR_SKIP );
		if ( 'div' == $args['style'] ) {
			$tag       = 'div';
			$add_below = 'comment';
		} else {
			$tag       = 'li';
			$add_below = 'div-comment';
		}
		$comment_id   = get_comment_ID();
		$gdpr_consent = get_comment_meta( $comment_id, 'rtrs_review_gdpr_consent', true );
		if ( 'hide' === $gdpr_consent ) {
			return;
		}
		?>

		<<?php echo esc_attr( $tag ); ?> <?php comment_class( empty( $args['has_children'] ) ? ' rtrs-main-review' : 'parent rtrs-main-review' ); ?> id="div-comment-<?php comment_ID(); ?>">
		<?php
		global $rtrs_post_id;

		$get_post_type = ( $rtrs_post_id ) ? get_post_type( $rtrs_post_id ) : get_post_type();
		$p_meta        = Functions::getMetaByPostType( $get_post_type );

		$layout = isset( $p_meta['review_layout'] ) ? $p_meta['review_layout'][0] : 'one';
		if ( in_array( $layout, [ 'three', 'four' ] ) && ! function_exists( 'rtrsp' ) ) {
			$layout = 'one';
		}
		// $comment_details = get_comment( $comment_id );
		$review_edit = rtrs()->get_options( 'rtrs_review_settings', [ 'review_edit', 'yes' ] );
		$classes     = implode( ' ', apply_filters( 'rtrs_each_review_classes', [ 'rtrs-each-review' ], $p_meta, $comment ) );

		Functions::get_template_part(
			'review/layout-' . $layout,
			[
				'comment'         => $comment,
				'add_below'       => $add_below,
				'depth'           => $depth,
				'args'            => $args,
				'p_meta'          => $p_meta,
				// 'comment_details' => $comment,
				'comment_classes' => $classes,
				'review_edit'     => $review_edit,
			]
		);
		?>
		<?php
	}
	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function has_reply_permition() {
		$reply_permition = rtrs()->get_options( 'rtrs_review_settings', [ 'comment_reply_permition', [ 'administrator', 'shop_manager' ] ] );
		return is_array( $reply_permition ) && count( array_intersect( $reply_permition, Functions::get_current_user_roles() ) ) ? true : false;
	}

	/**
	 * Read a single Media settings value.
	 *
	 * The Media subtab now persists to `rtrs_review_media_settings` (see
	 * MigrationV3); older installs may still hold values under the legacy
	 * `rtrs_media_settings` option. Prefer the canonical option and fall back
	 * to the legacy one so both new and un-migrated sites resolve correctly.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Field key.
	 * @param mixed  $default Default when the key is absent/empty in both options.
	 * @return mixed
	 */
	public static function getMediaOption( $key, $default = '' ) {
		$current = get_option( 'rtrs_review_media_settings', [] );
		if ( is_array( $current ) && isset( $current[ $key ] ) && '' !== $current[ $key ] && [] !== $current[ $key ] ) {
			return $current[ $key ];
		}

		$legacy = get_option( 'rtrs_media_settings', [] );
		if ( is_array( $legacy ) && isset( $legacy[ $key ] ) && '' !== $legacy[ $key ] && [] !== $legacy[ $key ] ) {
			return $legacy[ $key ];
		}

		return $default;
	}

	/**
	 * Whether non-logged-in (guest) visitors are allowed to upload review media.
	 *
	 * Controlled by the "Allow Guest Uploads" media setting. Disabled by default
	 * so the safe behaviour (login required) is preserved on existing sites.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function isGuestUploadEnabled() {
		return 'yes' === self::getMediaOption( 'allow_guest_upload', 'no' );
	}

	/**
	 * Whether the current visitor may upload review media (image/video).
	 *
	 * Logged-in users can always upload; guests only when the setting is on.
	 * Shared by the free image field and the Pro video field so both gate on
	 * the same rule.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function canUploadMedia() {
		return is_user_logged_in() || self::isGuestUploadEnabled();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function the_comment_form() {
		if ( is_singular( 'product' ) ) {
			if ( self::has_reply_permition() || 'no' === get_option( 'woocommerce_review_rating_verification_required' ) || wc_customer_bought_product( '', get_current_user_id(), get_the_ID() ) ) {
				comment_form();
			} else {
				?>
				<p class="woocommerce-verification-required">
					<?php esc_html_e( 'Only logged in customers who have purchased this product may leave a review.', 'review-schema' ); ?>
				</p>
				<?php
			}
		} else {
			comment_form();
		}
		wp_enqueue_script( 'comment-reply' );
	}
	public static function touch_time( $edit, $for_post, $tab_index, $multi, $comment_date ) {
		global $wp_locale;
		$post = get_post();

		if ( $for_post ) {
			$edit = ! ( in_array( $post->post_status, [ 'draft', 'pending' ], true ) && ( ! $post->post_date_gmt || '0000-00-00 00:00:00' === $post->post_date_gmt ) );
		}

		$tab_index_attribute = '';
		if ( (int) $tab_index > 0 ) {
			$tab_index_attribute = " tabindex=\"$tab_index\"";
		}

		$post_date = ( $for_post ) ? $post->post_date : $comment_date;
		$jj        = ( $edit ) ? mysql2date( 'd', $post_date, false ) : current_time( 'd' );
		$mm        = ( $edit ) ? mysql2date( 'm', $post_date, false ) : current_time( 'm' );
		$aa        = ( $edit ) ? mysql2date( 'Y', $post_date, false ) : current_time( 'Y' );
		$hh        = ( $edit ) ? mysql2date( 'H', $post_date, false ) : current_time( 'H' );
		$mn        = ( $edit ) ? mysql2date( 'i', $post_date, false ) : current_time( 'i' );
		$ss        = ( $edit ) ? mysql2date( 's', $post_date, false ) : current_time( 's' );

		$cur_jj = current_time( 'd' );
		$cur_mm = current_time( 'm' );
		$cur_aa = current_time( 'Y' );
		$cur_hh = current_time( 'H' );
		$cur_mn = current_time( 'i' );
		// sanitize text
		$month = '<label><span class="screen-reader-text">' . esc_html__( 'Month', 'review-schema' ) . '</span><select class="form-required" ' . ( $multi ? '' : 'id="mm" ' ) . 'name="mm"' . $tab_index_attribute . ">\n";
		for ( $i = 1; $i < 13; $i = $i + 1 ) {
			$monthnum  = zeroise( $i, 2 );
			$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
			$month    .= "\t\t\t" . '<option value="' . esc_attr( $monthnum ) . '" data-text="' . esc_attr( $monthtext ) . '" ' . selected( $monthnum, $mm, false ) . '>';
			/* translators: 1: Month number (01, 02, etc.), 2: Month abbreviation. */
			$month .= sprintf( '%1$s-%2$s', $monthnum, $monthtext ) . "</option>\n";
		}
		$month .= '</select></label>';

		$day    = '<label><span class="screen-reader-text">' . esc_html__( 'Day', 'review-schema' ) . '</span><input type="text" ' . ( $multi ? '' : 'id="jj" ' ) . 'name="jj" value="' . esc_attr( $jj ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
		$year   = '<label><span class="screen-reader-text">' . esc_html__( 'Year', 'review-schema' ) . '</span><input type="text" ' . ( $multi ? '' : 'id="aa" ' ) . 'name="aa" value="' . esc_attr( $aa ) . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
		$hour   = '<label><span class="screen-reader-text">' . esc_html__( 'Hour', 'review-schema' ) . '</span><input type="text" ' . ( $multi ? '' : 'id="hh" ' ) . 'name="hh" value="' . esc_attr( $hh ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
		$minute = '<label><span class="screen-reader-text">' . esc_html__( 'Minute', 'review-schema' ) . '</span><input type="text" ' . ( $multi ? '' : 'id="mn" ' ) . 'name="mn" value="' . esc_attr( $mn ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';

		echo '<div class="timestamp-wrap">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $month/$day/$year/$hour/$minute are built above with esc_html__/esc_attr on dynamic parts; tags are plugin-controlled.
		printf( '%1$s %2$s, %3$s at %4$s:%5$s', $month, $day, $year, $hour, $minute );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</div><input type="hidden" id="ss" name="ss" value="' . esc_attr( $ss ) . '" />';

		if ( $multi ) {
			return;
		}

		echo "\n\n";

		$map = [
			'mm' => [ $mm, $cur_mm ],
			'jj' => [ $jj, $cur_jj ],
			'aa' => [ $aa, $cur_aa ],
			'hh' => [ $hh, $cur_hh ],
			'mn' => [ $mn, $cur_mn ],
		];

		foreach ( $map as $timeunit => $value ) {
			list($unit, $curr) = $value;

			echo '<input type="hidden" id="hidden_' . esc_attr( $timeunit ) . '" name="hidden_' . esc_attr( $timeunit ) . '" value="' . esc_attr( $unit ) . '" />' . "\n";
			$cur_timeunit = 'cur_' . $timeunit;
			echo '<input type="hidden" id="' . esc_attr( $cur_timeunit ) . '" name="' . esc_attr( $cur_timeunit ) . '" value="' . esc_attr( $curr ) . '" />' . "\n";
		}
		?>
		<p>
			<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button"><?php esc_html_e( 'OK', 'review-schema' ); ?></a>
			<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js button-cancel"><?php esc_html_e( 'Cancel', 'review-schema' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Review time display with time formate
	 *
	 * @return string
	 */
	public static function comment_review_time( $comment ) {
		$p_meta              = Functions::getMetaByPostType( get_post_type() );
		$human_readable_time = isset( $p_meta['human-time-diff'] ) && $p_meta['human-time-diff'][0] == '1' ? false : true;

		if ( $human_readable_time ) {
			$time = human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ) . ' ' . esc_html__( 'ago', 'review-schema' );
		} else {
			$time = gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $comment->comment_date ) );
		}
		return $time;
	}

	/**
	 *  SchemaEngine AI Star Icon.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function review_stars( $rating, $dash_icon = false ) {
		ob_start();
		for ( $x = 0; $x < 5; $x++ ) {
			if ( $rating && floor( $rating ) - $x >= 1 ) {
				if ( $dash_icon ) {
					echo '<i class="dashicons dashicons-star-filled"></i>';
				} else {
					echo '<i class="rtrs-star"></i>';
				}
			} elseif ( $rating && $rating - $x > 0 ) {
				if ( $dash_icon ) {
					echo '<i class="dashicons dashicons-star-half"></i>';
				} else {
					echo '<i class="rtrs-star-half-alt"></i>';
				}
			} else {
				if ( $dash_icon ) {
					echo '<i class="dashicons dashicons-star-empty"></i>';
				} else {
					echo '<i class="rtrs-star-empty"></i>';
				}
			}
		}

		return ob_get_clean();
	}
	/**
	 *  SchemaEngine AI Entity Star Icon.
	 *
	 * @package SchemaEngine AI
	 *
	 * @since 1.0
	 */
	public static function review_entity_stars( $rating ) {
		ob_start();
		foreach ( [ 1, 2, 3, 4, 5 ] as $val ) {
			$score = $rating - $val;
			if ( $score >= 0 ) {
				echo '&#9733;';
			} elseif ( $score > -1 && $score < 0 ) {
				// half star will show full star in url
				echo '&#9733;';
			} else {
				echo '&#9734;';
			}
		}

		return ob_get_clean();
	}
}