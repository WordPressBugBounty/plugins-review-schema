<?php

namespace Rtrs\Controllers\Admin\Meta;

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Admin\Meta\FaqPageMeta;
use Rtrs\Modules\Schema\Helpers\SerpPreviewHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AddMetaBox {
	public function __construct() {
		// Actions.
		add_action( 'admin_head', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_data' ], 10, 2 );
	}

	public function add_meta_boxes() {
		if ( Functions::is_edit_page() ) {
			global $post;
			$post_type = $post->post_type;
			if ( rtrs()->getPostType() === $post_type || 'rtrs_affiliate' === $post_type ) {
				return;
			}
			if ( Functions::isEnableReviewByPostType( $post_type ) || Functions::schema_enabled() ) {
				add_meta_box(
					'rtrs_meta',
					esc_html__( 'SchemaEngine AI Settings', 'review-schema' ),
					[ $this, 'rtrs_single_meta_settings' ],
					[ $post_type ],
					'normal',
					'low'
				);
			}
		}
	}

	public function postType() {
		return apply_filters( 'rtrs_post_type', Functions::getPostTypes() );
	}

	public function rtrs_single_meta_settings( $post ) {

		$post = [
			'post' => $post,
		];
		wp_nonce_field( rtrs()->getNonceId(), rtrs()->getNonceId() );
		$post_type = $post['post']->post_type;

		// auto select tab.
		$tab = get_post_meta( get_the_ID(), '_rtrs_sc_tab', true );

		if ( ! $tab ) {
			$tab = ( Functions::isEnableReviewByPostType( $post_type ) ) ? 'review' : 'schema';
		} else {
			if ( Functions::schema_enabled() && $tab == 'review' ) {
				$tab = 'schema';
			}
		}

		$is_classic_editor = ! use_block_editor_for_post( $post['post'] );

		$review_tab  = ( $tab == 'review' ) ? 'active' : '';
		$schema_tab  = ( $tab == 'schema' ) ? 'active' : '';
		$preview_tab = ( $tab == 'preview' ) ? 'active' : '';
		$serp_tab    = ( $tab == 'serp' ) ? 'active' : '';
		$faqpage_tab = ( $tab == 'faqpage' ) ? 'active' : '';

		$html  = null;
		$html .= '<div id="sc-tabs" class="rtrs-tab-container">';
		$html .= '<ul class="rtrs-tab-nav">';
		if ( Functions::isEnableReviewByPostType( $post_type ) ) {
			$html .= '<li class="' . esc_attr( $review_tab ) . '"><a href="#sc-review"><i class="dashicons dashicons-star-filled"></i>' . esc_html__( 'Review', 'review-schema' ) . '</a></li>';
		}
		if ( $is_classic_editor ) {
			$html .= '<li class="' . esc_attr( $faqpage_tab ) . '"><a href="#sc-faqpage"><i class="dashicons dashicons-format-chat"></i>' . esc_html__( 'FAQ Content', 'review-schema' ) . '</a></li>';
		}
		$html .= '<li class="' . esc_attr( $schema_tab ) . '"><a href="#sc-schema"><i class="dashicons dashicons-editor-table"></i>' . esc_html__( 'Schema', 'review-schema' ) . '</a></li>';
		if ( Functions::schema_enabled() ) {
			$html .= '<li class="' . esc_attr( $preview_tab ) . '"><a href="#sc-schema-preview"><i class="dashicons dashicons-editor-table"></i>' . esc_html__( 'Schema Preview', 'review-schema' ) . '</a></li>';
			$html .= '<li class="' . esc_attr( $serp_tab ) . '"><a href="#sc-serp"><i class="dashicons dashicons-search"></i>' . esc_html__( 'SERP', 'review-schema' ) . '</a></li>';
		}
		if ( Functions::schema_enabled() && 'yes' === \Rtrs\AI\AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			$html .= '<li><a href="#sc-generate-ai"><svg width="17" height="21" viewBox="0 0 17 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.37822 4.38293L6.95178 5.97575C7.5889 7.74356 8.98101 9.13566 10.7488 9.77279L12.3416 10.3463C12.4852 10.3985 12.4852 10.6021 12.3416 10.6535L10.7488 11.227C8.98101 11.8642 7.5889 13.2563 6.95178 15.0241L6.37822 16.6169C6.32608 16.7605 6.12252 16.7605 6.07109 16.6169L5.49753 15.0241C4.86041 13.2563 3.4683 11.8642 1.70049 11.227L0.107676 10.6535C-0.0358919 10.6013 -0.0358919 10.3978 0.107676 10.3463L1.70049 9.77279C3.4683 9.13566 4.86041 7.74356 5.49753 5.97575L6.07109 4.38293C6.12252 4.23865 6.32608 4.23865 6.37822 4.38293Z" fill="currentColor"></path><path d="M13.548 0.555177L13.8387 1.36158C14.1616 2.25656 14.8666 2.96154 15.7615 3.28439L16.568 3.5751C16.6408 3.60152 16.6408 3.70438 16.568 3.73081L15.7615 4.02151C14.8666 4.34436 14.1616 5.04934 13.8387 5.94432L13.548 6.75073C13.5216 6.82358 13.4187 6.82358 13.3923 6.75073L13.1016 5.94432C12.7788 5.04934 12.0738 4.34436 11.1788 4.02151L10.3724 3.73081C10.2995 3.70438 10.2995 3.60152 10.3724 3.5751L11.1788 3.28439C12.0738 2.96154 12.7788 2.25656 13.1016 1.36158L13.3923 0.555177C13.4187 0.481608 13.5223 0.481608 13.548 0.555177Z" fill="currentColor"></path><path d="M13.548 14.2498L13.8387 15.0562C14.1616 15.9512 14.8666 16.6562 15.7615 16.979L16.568 17.2697C16.6408 17.2962 16.6408 17.399 16.568 17.4254L15.7615 17.7161C14.8666 18.039 14.1616 18.744 13.8387 19.639L13.548 20.4454C13.5216 20.5182 13.4187 20.5182 13.3923 20.4454L13.1016 19.639C12.7788 18.744 12.0738 18.039 11.1788 17.7161L10.3724 17.4254C10.2995 17.399 10.2995 17.2962 10.3724 17.2697L11.1788 16.979C12.0738 16.6562 12.7788 15.9512 13.1016 15.0562L13.3923 14.2498C13.4187 14.177 13.5223 14.177 13.548 14.2498Z" fill="currentColor"></path></svg>' . esc_html__( 'Generate with AI', 'review-schema' ) . '</a></li>';
		}
		$html .= '</ul>';

		$review_tab    = ( 'review' === $tab ) ? 'display: block' : '';
		$schema_tab    = ( 'schema' === $tab ) ? 'display: block' : '';
		$preview_tab   = ( 'preview' === $tab ) ? 'display: block' : '';
		$serp_style    = ( 'serp' === $tab ) ? 'display: block' : '';
		$faqpage_style = ( 'faqpage' === $tab ) ? 'display: block' : '';

		$html .= '<input type="hidden" id="_rtrs_sc_tab" name="_rtrs_sc_tab" value="' . esc_attr( $tab ) . '" />';

		$html .= '<div id="sc-review" class="rtrs-tab-content" style="' . esc_attr( $review_tab ) . '">';
		if ( Functions::isEnableReviewByPostType( $post_type ) ) {
			$html .= rtrs()->render( 'metas.single.review', $post, true );
			$html .= rtrs()->render( 'metas.single.review-graph', $post, true );
		} else {
			$html .= '<div class="rtrs-preview-message"><p> <span class="dashicons dashicons-info"></span>' . esc_html__( 'Review Is Not Enabled.', 'review-schema' ) . '</p></div>';
		}
		$html .= '</div>';

		$html          .= '<div id="sc-schema" class="rtrs-tab-content" style="' . esc_attr( $schema_tab ) . '">';
		$has_ai_schema  = ! empty( get_post_meta( $post['post']->ID, \Rtrs\AI\AIInit::META_KEY, true ) );
		if ( ! Functions::schema_enabled() ) {
			$html .= '<div class="rtrs-preview-message"><p><span class="dashicons dashicons-info"></span>' . esc_html__( 'Schema Is Not Enabled.', 'review-schema' ) . '</p></div>';
		} else {
			if ( $has_ai_schema ) {
				$html .= '<div class="rtrs-ai-schema-notice">';
				$html .= '<span class="dashicons dashicons-info"></span>';
				$html .= '<div>';
				$html .= '<p>' . esc_html__( 'Schema is generated by AI. Please check the AI panel to view or edit.', 'review-schema' ) . '</p>';
				$html .= '<p>' . esc_html__( 'After deleting AI data, manual generation fields will be visible.', 'review-schema' ) . '</p>';
				$html .= '</div>';
				$html .= '</div>';
			}
			// Schema Report section.
			$html .= '<div id="rtrs-schema-report" class="rtrs-schema-report' . ( $has_ai_schema ? ' rtrs-hidden' : '' ) . '">';
			$html .= '<div class="rtrs-schema-report__inner"></div>';
			$html .= '</div>';

			$html .= '<div class="rtrs-schema-fields' . ( $has_ai_schema ? ' rtrs-hidden' : '' ) . '">';
			$html .= rtrs()->render( 'metas.single.schema', $post, true );
			$html .= '</div>';
		}
		$html .= '</div>';

		if ( Functions::schema_enabled() ) {
			$html .= '<div id="sc-schema-preview" class="rtrs-tab-content" style="' . esc_attr( $preview_tab ) . '">';
			$html .= rtrs()->render( 'metas.single.schema-preview', $post, true );
			$html .= '</div>';
		}
		if ( Functions::schema_enabled() ) {
			if ( $is_classic_editor ) {
				$html .= '<div id="sc-faqpage" class="rtrs-tab-content" style="' . esc_attr( $faqpage_style ) . '">';
				$html .= rtrs()->render( 'metas.single.faqpage', $post, true );
				$html .= '</div>';
			}
		}
		if ( Functions::schema_enabled() ) {
			$html    .= '<div id="sc-serp" class="rtrs-tab-content" style="' . esc_attr( $serp_style ) . '">';
			$serpData = SerpPreviewHelper::extract( $post['post']->ID );
			$html    .= rtrs()->render( 'metas.single.serp-preview', compact( 'serpData' ), true );
			$html    .= '</div>';
		}
		if ( Functions::schema_enabled() && 'yes' === \Rtrs\AI\AIInit::getSetting( 'ai_enabled', 'no' ) ) {
			$html .= '<div id="sc-generate-ai" class="rtrs-tab-content">';
			$html .= '<div class="rtrs-ai-tab-message">';
			$html .= '<span class="dashicons dashicons-arrow-right-alt"></span>';
			$html .= '<p>' . esc_html__( 'Use the SchemaEngine AI panel in the right sidebar to auto-generate structured data for this post.', 'review-schema' ) . '</p>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>'; // wrap div
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $html is built with esc_attr/esc_html/wp_kses on dynamic parts.
		echo $html;
	}

	public function sanitize_field( $type, $value ) {
		$fValue = '';
		switch ( $type ) {
			case 'textarea':
				$fValue = isset( $value ) ? sanitize_textarea_field( $value ) : null;
				break;
			case 'text':
			case 'select':
			case 'tab':
			case 'radio-image':
				$fValue = isset( $value ) ? sanitize_text_field( $value ) : null;
				break;

			case 'url':
				$fValue = isset( $value ) ? esc_url_raw( $value ) : null;
				break;

			case 'number':
			case 'switch':
			case 'checkbox':
			case 'image':
				$fValue = isset( $value ) ? absint( $value ) : null;
				break;

			case 'float':
				$fValue = isset( $value ) ? floatval( $value ) : null;
				break;

			case 'gallery':
				$fValue = isset( $value ) && is_array( $value ) ? array_map( 'absint', $value ) : null;
				break;

			case 'repeater':
				$fValue = isset( $value ) && is_array( $value ) ? array_map( 'sanitize_text_field', array_filter( $value ) ) : null;
				break;

			case 'color':
				$fValue = isset( $value ) ? sanitize_hex_color( $value ) : null;
				break;

			case 'style':
				$fValue = isset( $value ) ? array_map( 'sanitize_text_field', $value ) : null;
				break;

			default:
				$fValue = isset( $value ) ? sanitize_text_field( $value ) : null;
				break;
		}

		return $fValue;
	}

	public function searchArray( $value, $key, $array ) {
		foreach ( $array as $k => $val ) {
			if ( ! empty( $val[ $key ] ) && $val[ $key ] == $value ) {
				return $k;
			}
		}

		return null;
	}

	public function save_meta_data( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( ! wp_verify_nonce( Functions::get_nonce(), rtrs()->getNonceId() ) ) {
			return $post_id;
		}

		$meta_options = null;
		if ( rtrs()->getPostType() == $post->post_type ) {
			$meta_options = Functions::post_type_rtrs_meta();
		} elseif ( 'rtrs_affiliate' == $post->post_type ) {
			$meta_options = Functions::affiliate_meta();
		} else {
			$meta_options    = Functions::single_page_meta_for_review_and_schema();
			$selected_schema = [];
			foreach ( $meta_options as $value ) {
				if ( in_array( ( $value['type'] ?? '' ), [ 'group', 'info' ], true ) ) {
					continue;
				}
				$selected_schema[] = $value;
			}
			if ( isset( $_POST['_rtrs_rich_snippet_cat'] ) && is_array( $_POST['_rtrs_rich_snippet_cat'] ) ) {
				$rtrs_cats = array_map( 'sanitize_text_field', wp_unslash( $_POST['_rtrs_rich_snippet_cat'] ) );
				foreach ( $rtrs_cats as $value ) {
					$index = $this->searchArray( 'rtrs_' . $value . '_schema', 'name', $meta_options );
					if ( $index != null ) {
						$selected_schema[] = $meta_options[ $index ];
					}
				}
			}

			// Re-add FAQPage dedicated group (not part of rich snippet dropdown).
			$faqpage_index = $this->searchArray( FaqPageMeta::META_KEY, 'name', $meta_options );
			if ( $faqpage_index !== null ) {
				$selected_schema[] = $meta_options[ $faqpage_index ];
			}

			$meta_options = $selected_schema;
		}
		$skip_field_types = [ 'heading', 'auto-fill' ];
		foreach ( $meta_options as $field ) {
			if ( in_array( ( $field['type'] ?? '' ), $skip_field_types, true ) ) {
				continue;
			}
			if ( $field['type'] == 'group' ) {
				// escape pro field
				if ( $field['name'] != 'rating_criteria' ) {
					if ( isset( $field['is_pro'] ) && ! function_exists( 'rtrsp' ) ) {
						continue;
					}
				}
				// save group field
				$groupValue = [];
				// remove heading type from groups field.
				foreach ( $field['fields'] as $key => $single_meta ) {
					if ( in_array( ( $single_meta['type'] ?? '' ), $skip_field_types, true ) ) {
						unset( $field['fields'][ $key ] );
					}
				}
				// after remove heading type sort again.
				$field['fields'] = array_values( $field['fields'] );
				if ( isset( $_REQUEST[ $field['name'] ] ) && is_array( $_REQUEST[ $field['name'] ] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested group values are sanitized per-field below via $this->sanitize_field().
					$rtrs_group_input = wp_unslash( $_REQUEST[ $field['name'] ] );
					foreach ( $rtrs_group_input as $key => $group_fields ) {
						$i = 0;
						foreach ( $group_fields as $group_key => $group_field ) {
							// if 1st nested group
							if ( $field['fields'][ $i ]['type'] == 'group' ) {
								foreach ( $group_field as $group_two_key => $group_two_field ) {
									foreach ( $group_two_field as $group_three_key => $group_three_field ) {
										$nested_index = array_search( $group_three_key, array_column( $field['fields'][ $i ]['fields'], 'name' ) );
										// if 2nd nested group
										if ( $field['fields'][ $i ]['fields'][ $nested_index ]['type'] == 'group' ) {
											foreach ( $group_three_field as $group_four_key => $group_four_field ) {
												foreach ( $group_four_field as $group_five_key => $group_five_field ) {
													$second_nested_index = array_search( $group_five_key, array_column( $field['fields'][ $i ]['fields'][ $nested_index ]['fields'], 'name' ) );
													$groupValue[ $key ][ $group_key ][ $group_two_key ][ $group_three_key ][ $group_four_key ][ $group_five_key ] = $this->sanitize_field( $field['fields'][ $i ]['fields'][ $nested_index ]['fields'][ $second_nested_index ]['type'], $group_five_field );
												}
											}
										} else {
											$groupValue[ $key ][ $group_key ][ $group_two_key ][ $group_three_key ] = $this->sanitize_field( $field['fields'][ $i ]['fields'][ $nested_index ]['type'], $group_three_field );
										}
									}
								}
							} else {
								$groupValue[ $key ][ $group_key ] = $this->sanitize_field( $field['fields'][ $i ]['type'], $group_field );
							}
							$i++;
						}
					}
				}

				// Filter out FAQ entries with empty question or answer.
				if ( $field['name'] === FaqPageMeta::META_KEY ) {
					$groupValue = array_filter(
						$groupValue,
						function ( $entry ) {
							return ! empty( trim( $entry['question'] ?? '' ) ) && ! empty( trim( $entry['answer'] ?? '' ) );
						}
					);
					$groupValue = array_values( $groupValue );
				}

				update_post_meta( $post_id, $field['name'], $groupValue );
			} else {
				if ( isset( $field['multiple'] ) ) {
					if ( $field['multiple'] ) {
						delete_post_meta( $post_id, $field['name'] );
						$mValueA = isset( $_REQUEST[ $field['name'] ] ) && is_array( $_REQUEST[ $field['name'] ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST[ $field['name'] ] ) ) : [];
						if ( is_array( $mValueA ) && ! empty( $mValueA ) ) {
							foreach ( $mValueA as $item ) {
								add_post_meta( $post_id, $field['name'], trim( $item ) );
							}
						}
					}
				} else {
					// escape pro field.
					if ( isset( $field['is_pro'] ) && ! function_exists( 'rtrsp' ) ) {
						continue;
					}
					if ( isset( $_REQUEST[ $field['name'] ] ) ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via $this->sanitize_field() per-type below.
						$rtrs_field_input = is_array( $_REQUEST[ $field['name'] ] ) ? wp_unslash( $_REQUEST[ $field['name'] ] ) : wp_unslash( $_REQUEST[ $field['name'] ] );
						$fValue           = $this->sanitize_field( $field['type'], $rtrs_field_input );
						update_post_meta( $post_id, $field['name'], $fValue );
					} elseif ( $field['type'] == 'switch' || $field['type'] == 'checkbox' ) {
						update_post_meta( $post_id, $field['name'], null );
					}
				}
			}
		}

		// Save current tab.
		$sc_tab = isset( $_REQUEST['_rtrs_sc_tab'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_rtrs_sc_tab'] ) ) : '';
		update_post_meta( $post_id, '_rtrs_sc_tab', $sc_tab );

		// Validate affiliate pricing: regular_price must be >= offer_price.
		if ( 'rtrs_affiliate' === $post->post_type ) {
			$regular_price = get_post_meta( $post_id, 'regular_price', true );
			$offer_price   = get_post_meta( $post_id, 'offer_price', true );

			if ( '' !== $regular_price && '' !== $offer_price && is_numeric( $regular_price ) && is_numeric( $offer_price ) ) {
				if ( floatval( $regular_price ) < floatval( $offer_price ) ) {
					// Swap the prices so regular is always >= offer.
					update_post_meta( $post_id, 'regular_price', $offer_price );
					update_post_meta( $post_id, 'offer_price', $regular_price );
					set_transient( 'rtrs_admin_notice', esc_html__( 'Regular Price cannot be less than Offer Price. The values have been swapped automatically.', 'review-schema' ), 30 );
				}
			}
		}

		// generate shortcode.
		if ( rtrs()->getPostType() == $post->post_type ) {
			Functions::generatorShortCodeCss( $post_id, 'review' );
		} elseif ( 'rtrs_affiliate' == $post->post_type ) {
			Functions::generatorShortCodeCss( $post_id, 'affiliate' );
		}
	} // end function
}
