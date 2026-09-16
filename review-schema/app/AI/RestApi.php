<?php
/**
 * REST API - Handles AJAX endpoints for the block editor sidebar panel.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

use Rtrs\Modules\Schema\Hooks\ElementorFaq;

defined( 'ABSPATH' ) || exit;

class RestApi {

	const REST_NAMESPACE = 'rtrs-ai/v1';

	public function register_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/generate/(?P<post_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_generate' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id'     => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'force_types' => [
						'type'     => 'array',
						'required' => false,
						'items'    => [ 'type' => 'string' ],
					],
					'faq_count'   => [
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'include_faq' => [
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/save-faq/(?P<post_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save_faq' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/generate-faq/(?P<post_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_generate_faq' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id'   => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'faq_count' => [
						'type'              => 'integer',
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/save/(?P<post_id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_save' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/delete/(?P<post_id>\d+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_delete' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/preview/(?P<post_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_preview' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/report/(?P<post_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_report' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'post_id' => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Return the full analysis report (validation, evaluation, SEO/AEO/GEO).
	 *
	 * REST equivalent of the `rtrs_get_schema_report` admin-ajax action. Used by
	 * the Elementor editor SEO report panel so the request rides the REST
	 * `X-WP-Nonce` (kept fresh by WordPress heartbeat) instead of a page-load
	 * static admin-ajax nonce that expires while the editor sits open.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_report( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );

		$report = \Rtrs\Modules\Schema\Ajax\SchemaReportAjax::build_report( $post_id );

		if ( is_wp_error( $report ) ) {
			return new \WP_REST_Response( [ 'message' => $report->get_error_message() ], 400 );
		}

		return new \WP_REST_Response( $report, 200 );
	}

	public function check_edit_permission( $request ) {
		$post_id = $request->get_param( 'post_id' );

		// Standard WordPress capability check.
		if ( current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		// SureCart products use a custom capability.
		$post = get_post( $post_id );
		if ( $post && 'sc_product' === $post->post_type ) {
			return current_user_can( 'edit_sc_products' );
		}

		// FluentCart products.
		if ( $post && 'fluent-products' === $post->post_type ) {
			return current_user_can( 'manage_options' );
		}

		return false;
	}

	public function handle_generate( $request ) {
		$post_id     = $request->get_param( 'post_id' );
		$force_types = $request->get_param( 'force_types' );
		$extractor   = new ContentExtractor();

		// Sanitize and filter force_types.
		if ( ! empty( $force_types ) && is_array( $force_types ) ) {
			$force_types = array_values( array_filter( array_map( 'sanitize_text_field', $force_types ) ) );
		} else {
			$force_types = [];
		}

		if ( ! empty( $force_types ) ) {
			$primary   = array_shift( $force_types );
			$secondary = $force_types;

			$classification = [
				'primary_type'    => $primary,
				'secondary_types' => $secondary,
				'confidence'      => 100,
				'reasoning'       => __( 'Manually selected by user.', 'review-schema' ),
			];
		} else {
			// Auto-detect: delegate to filter. Default returns WP_Error (requires Pro).
			$classification = apply_filters(
				'rtrs_ai_auto_classify',
				new \WP_Error(
					'pro_required',
					__( 'Auto-detect requires Pro. Please select at least one schema type.', 'review-schema' ),
					[ 'status' => 403 ]
				),
				$post_id
			);

			if ( is_wp_error( $classification ) ) {
				return $classification;
			}
		}

		// Gate pro-only schema types at generation time.
		$pro_gated_types = apply_filters( 'rtrs_ai_pro_gated_types', AIInit::getProSchemaTypes() );

		if ( in_array( $classification['primary_type'], $pro_gated_types, true ) && ! function_exists( 'rtrsp' ) ) {
			return new \WP_Error(
				'pro_required',
				sprintf(
					/* translators: %s: Schema type name */
					__( '%s schema generation requires Pro.', 'review-schema' ),
					$classification['primary_type']
				),
				[ 'status' => 403 ]
			);
		}

		// Filter out pro-gated secondary types when free.
		if ( ! function_exists( 'rtrsp' ) ) {
			$classification['secondary_types'] = array_values(
				array_filter(
					$classification['secondary_types'],
					function ( $type ) use ( $pro_gated_types ) {
						return ! in_array( $type, $pro_gated_types, true );
					}
				)
			);
		}

		$full_payload = $extractor->extract( $post_id );

		if ( is_wp_error( $full_payload ) ) {
			return $full_payload;
		}

		// If the post has an FAQ block or existing FAQ meta, skip AI FAQPage generation.
		$has_existing_faq = ! empty( $full_payload['structural']['has_faq_block'] )
			|| ! empty( $full_payload['structural']['has_faq_meta'] );

		if ( $has_existing_faq ) {
			if ( 'FAQPage' === $classification['primary_type'] ) {
				$classification['primary_type'] = 'Article';
			}

			$classification['secondary_types'] = array_values(
				array_filter(
					$classification['secondary_types'],
					function ( $type ) {
						return 'FAQPage' !== $type;
					}
				)
			);
		}

		// FAQPage is generated in a SEPARATE AI call (below) rather than merged
		// into the main schema request. Asking the model for the full schema
		// graph AND every FAQ pair in one response overflows the output token
		// budget and truncates — the cause of the recurring "AI response was
		// truncated" error on the "Schema + FAQs" action. Splitting keeps each
		// call within max_tokens on every model.
		$include_faq  = $request->get_param( 'include_faq' );
		$generate_faq = $include_faq
			&& ! $has_existing_faq
			&& 'FAQPage' !== $classification['primary_type']
			&& ! in_array( 'FAQPage', $classification['secondary_types'], true );

		$schema_extractor = new SchemaExtractor();
		$extract_options  = [];
		$faq_count        = $request->get_param( 'faq_count' );
		if ( $faq_count ) {
			$extract_options['faq_count'] = (int) $faq_count;
		}
		$schemas = $schema_extractor->extract(
			$full_payload,
			$classification['primary_type'],
			$classification['secondary_types'],
			$extract_options
		);

		if ( is_wp_error( $schemas ) ) {
			return $schemas;
		}

		if ( empty( $schemas ) ) {
			return new \WP_Error(
				'empty_schema',
				sprintf(
					/* translators: %s: Schema type name */
					__( 'AI could not generate %s schema from the available content. Please try again.', 'review-schema' ),
					$classification['primary_type']
				),
				[ 'status' => 422 ]
			);
		}

		// Generate the FAQPage in its own request and append it. Keeping this
		// separate from the main schema call is what prevents the combined
		// output from exceeding the model's max_tokens (the "Schema + FAQs"
		// truncation issue). A FAQ failure degrades to schema-only rather than
		// failing the whole generation.
		if ( $generate_faq ) {
			$faq_extractor = new SchemaExtractor();
			$faq_schemas   = $faq_extractor->extract( $full_payload, 'FAQPage', [], $extract_options );

			if ( ! is_wp_error( $faq_schemas ) && ! empty( $faq_schemas ) ) {
				foreach ( $faq_schemas as $faq_node ) {
					if ( 'FAQPage' === ( $faq_node['@type'] ?? '' ) ) {
						$schemas[] = $faq_node;

						if ( ! in_array( 'FAQPage', $classification['secondary_types'], true ) ) {
							$classification['secondary_types'][] = 'FAQPage';
						}
					}
				}
			}
		}

		// When existing FAQ meta data exists, build FAQPage schema from it
		// instead of AI-generating it — reuses the manually curated FAQ data.
		if ( $has_existing_faq && ! empty( $full_payload['structural']['has_faq_meta'] ) ) {
			$faq_schema = self::build_faqpage_from_meta( $post_id );
			if ( $faq_schema ) {
				$schemas[] = $faq_schema;
			}
		}

		// Merge with global schemas for validation/evaluation preview.
		$renderer       = new SchemaRenderer();
		$global_schemas = $renderer->build_global_schemas( $post_id, $schemas );
		$full_graph     = array_merge( $schemas, $global_schemas );
		$full_graph     = apply_filters( 'rtrs_ai_schema_before_render', $full_graph, $post_id );

		$validator  = new SchemaValidator();
		$validation = $validator->validate( $full_graph );

		$evaluation = apply_filters( 'rtrs_ai_schema_evaluation', self::get_dummy_evaluation(), $full_graph );

		return rest_ensure_response(
			[
				'success'        => true,
				'schemas'        => $schemas,
				'full_graph'     => $full_graph,
				'classification' => $classification,
				'validation'     => $validation,
				'evaluation'     => $evaluation,
			]
		);
	}

	public function handle_save( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$body    = $request->get_json_params();
		$is_edit = ! empty( $body['is_edit'] );

		// Edit-save is a Pro feature.
		if ( $is_edit && ! function_exists( 'rtrsp' ) ) {
			return new \WP_Error(
				'pro_required',
				__( 'Editing schema requires the Pro version.', 'review-schema' ),
				[ 'status' => 403 ]
			);
		}

		$schemas      = AIInit::normalizeSchemaData( $body['schemas'] ?? [] );
		$schema_type  = $body['schema_type'] ?? '';
		$schema_types = $body['schema_types'] ?? [];
		$confidence   = $body['confidence'] ?? 0;

		if ( empty( $schemas ) ) {
			return new \WP_Error( 'no_schema', __( 'No schema data provided.', 'review-schema' ), [ 'status' => 400 ] );
		}

		// Filter out global node types before saving (safety net).
		$always_global = [ 'WebPage', 'WebSite', 'BreadcrumbList', 'Organization', 'Person' ];
		$entity_id     = AIInit::getEntityData()['entity_id'];

		$content_schemas = array_values(
			array_filter(
				$schemas,
				function ( $s ) use ( $always_global, $entity_id ) {
					if ( in_array( $s['@type'] ?? '', $always_global, true ) ) {
						return false;
					}

					// Filter entity nodes by @id pattern (#organization / #person).
					if ( ! empty( $s['@id'] ) && preg_match( '/#(organization|person)$/i', $s['@id'] ) ) {
						return false;
					}

					return true;
				}
			)
		);

		// Extract FAQPage data for block insertion, then strip from saved schemas
		// (GutenbergFaq block will handle FAQPage schema at render time).
		$faq_main_entity = null;
		$content_schemas = array_values(
			array_filter(
				$content_schemas,
				function ( $s ) use ( &$faq_main_entity ) {
					if ( 'FAQPage' === ( $s['@type'] ?? '' ) && ! empty( $s['mainEntity'] ) ) {
						$faq_main_entity = $s['mainEntity'];

						return false;
					}

					return true;
				}
			)
		);

		// Save content-only schemas as JSON string to post meta.
		// wp_slash() counteracts the wp_unslash() inside update_post_meta(),
		// which would otherwise strip JSON escape backslashes (e.g. \" → ") and corrupt the JSON.
		update_post_meta( $post_id, AIInit::META_KEY, wp_slash( wp_json_encode( $content_schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		wp_cache_delete( "rtrs_ai_schemas_{$post_id}", 'rtrs_ai' );

		// Sync FAQPage data: for Elementor pages append widget only,
		// for other editors save to dedicated meta for frontend rendering.
		if ( $faq_main_entity ) {
			$is_elementor = ElementorFaq::is_elementor_post( $post_id );

			if ( $is_elementor ) {
				$faq_pairs = [];
				$entities  = isset( $faq_main_entity['@type'] ) ? [ $faq_main_entity ] : $faq_main_entity;
				foreach ( $entities as $entity ) {
					if ( ( $entity['@type'] ?? '' ) === 'Question'
						&& ! empty( $entity['name'] )
						&& ! empty( $entity['acceptedAnswer']['text'] )
					) {
						$faq_pairs[] = [
							'question' => $entity['name'],
							'answer'   => $entity['acceptedAnswer']['text'],
						];
					}
				}
				ElementorFaq::append_faq_widget( $post_id, $faq_pairs );
			} else {
				$this->save_faq_main_entity_to_meta( $post_id, $faq_main_entity );
			}
		}

		// Store primary type (backward compatible) or all types if provided.
		if ( ! empty( $schema_types ) && is_array( $schema_types ) ) {
			$clean_types = array_values( array_filter( array_map( 'sanitize_text_field', $schema_types ) ) );
			update_post_meta( $post_id, AIInit::TYPE_META_KEY, implode( ',', $clean_types ) );
		} elseif ( $schema_type ) {
			update_post_meta( $post_id, AIInit::TYPE_META_KEY, sanitize_text_field( $schema_type ) );
		}

		if ( $confidence ) {
			update_post_meta( $post_id, AIInit::CONFIDENCE_META_KEY, (int) $confidence );
		}

		// Validate/evaluate the full graph (content + global).
		$renderer       = new SchemaRenderer();
		$global_schemas = $renderer->build_global_schemas( $post_id, $content_schemas );
		$full_graph     = array_merge( $content_schemas, $global_schemas );
		$full_graph     = apply_filters( 'rtrs_ai_schema_before_render', $full_graph, $post_id );

		$validator  = new SchemaValidator();
		$validation = $validator->validate( $full_graph );

		$evaluation = apply_filters( 'rtrs_ai_schema_evaluation', self::get_dummy_evaluation(), $full_graph );

		// Return the saved schema type so the panel can update the dropdown.
		$saved_type = get_post_meta( $post_id, AIInit::TYPE_META_KEY, true );

		return rest_ensure_response(
			[
				'success'     => true,
				'schemas'     => $content_schemas,
				'faq_data'    => $faq_main_entity,
				'full_graph'  => $full_graph,
				'validation'  => $validation,
				'evaluation'  => $evaluation,
				'schema_type' => $saved_type,
			]
		);
	}

	/**
	 * Dummy evaluation result for the free version mockup.
	 *
	 * Returns placeholder data so the Quality Score UI section renders
	 * (behind the Pro overlay) for feature promotion.
	 *
	 * @return array
	 */
	public static function get_dummy_evaluation() {
		$placeholder = function ( $label, $score ) {
			return [
				'score'   => $score,
				'label'   => $label,
				'details' => [],
			];
		};

		return [
			'overall'  => 78,
			'grade'    => 'B+',
			'criteria' => [
				'completeness'     => $placeholder( __( 'Completeness', 'review-schema' ), 85 ),
				'rich_results'     => $placeholder( __( 'Rich Results', 'review-schema' ), 90 ),
				'cross_references' => $placeholder( __( 'Cross-References', 'review-schema' ), 75 ),
				'content_depth'    => $placeholder( __( 'Content Depth', 'review-schema' ), 70 ),
				'identifiers'      => $placeholder( __( 'Identifiers', 'review-schema' ), 65 ),
				'graph_structure'  => $placeholder( __( 'Graph Structure', 'review-schema' ), 80 ),
			],
		];
	}

	/**
	 * Save confirmed FAQ data to post meta (_rtrs_faqpage_data).
	 *
	 * Accepts an array of Question objects (same format returned by /generate-faq)
	 * and persists them so the FAQ metabox reflects the AI-generated content.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_save_faq( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$body    = $request->get_json_params();
		$items   = $body['faq_data'] ?? [];

		if ( empty( $items ) || ! is_array( $items ) ) {
			return new \WP_Error(
				'no_faq_data',
				__( 'No FAQ data provided.', 'review-schema' ),
				[ 'status' => 400 ]
			);
		}

		$faq_data = [];
		foreach ( $items as $item ) {
			// Accept both mapped format (question/answer) and raw schema format (name/acceptedAnswer).
			$question = sanitize_text_field( $item['question'] ?? $item['name'] ?? '' );
			$answer   = sanitize_textarea_field( $item['answer'] ?? $item['acceptedAnswer']['text'] ?? '' );

			if ( $question && $answer ) {
				$faq_data[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}

		if ( empty( $faq_data ) ) {
			return new \WP_Error(
				'invalid_faq_data',
				__( 'FAQ data is invalid or empty.', 'review-schema' ),
				[ 'status' => 400 ]
			);
		}

		// For Elementor pages: only append widget, skip meta save.
		if ( ElementorFaq::is_elementor_post( $post_id ) ) {
			ElementorFaq::append_faq_widget( $post_id, $faq_data );
		} else {
			update_post_meta( $post_id, '_rtrs_faqpage_data', $faq_data );
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'FAQ data saved.', 'review-schema' ),
				'count'   => count( $faq_data ),
			]
		);
	}

	/**
	 * Generate FAQ content only — no schema saved to post meta.
	 *
	 * Forces FAQPage extraction and returns the raw question/answer pairs
	 * so the JS can insert them into the rtrs/faq block (block editor)
	 * or as HTML into TinyMCE (classic editor).
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_generate_faq( $request ) {
		$post_id   = $request->get_param( 'post_id' );
		$faq_count = $request->get_param( 'faq_count' );

		$extractor    = new ContentExtractor();
		$full_payload = $extractor->extract( $post_id );

		if ( is_wp_error( $full_payload ) ) {
			return $full_payload;
		}

		$extract_options = [];
		if ( $faq_count ) {
			$extract_options['faq_count'] = (int) $faq_count;
		}

		$schema_extractor = new SchemaExtractor();
		$schemas          = $schema_extractor->extract( $full_payload, 'FAQPage', [], $extract_options );

		if ( is_wp_error( $schemas ) ) {
			return $schemas;
		}

		// Find the FAQPage schema and pull out its mainEntity.
		$faq_main_entity = null;
		foreach ( $schemas as $schema ) {
			if ( 'FAQPage' === ( $schema['@type'] ?? '' ) && ! empty( $schema['mainEntity'] ) ) {
				$faq_main_entity = $schema['mainEntity'];
				break;
			}
		}

		if ( empty( $faq_main_entity ) ) {
			return new \WP_Error(
				'faq_generation_failed',
				__( 'AI could not generate FAQ content from the available content. Please try again.', 'review-schema' ),
				[ 'status' => 422 ]
			);
		}

		// Normalise to a flat array of Question objects.
		$entities = isset( $faq_main_entity['@type'] ) ? [ $faq_main_entity ] : $faq_main_entity;
		$faq_data = array_values(
			array_filter(
				$entities,
				function ( $e ) {
					return 'Question' === ( $e['@type'] ?? '' )
						&& ! empty( $e['name'] )
						&& ! empty( $e['acceptedAnswer']['text'] );
				}
			)
		);

		if ( empty( $faq_data ) ) {
			return new \WP_Error(
				'faq_generation_failed',
				__( 'AI could not generate FAQ content from the available content. Please try again.', 'review-schema' ),
				[ 'status' => 422 ]
			);
		}

		return rest_ensure_response(
			[
				'success'  => true,
				'faq_data' => $faq_data,
			]
		);
	}

	public function handle_delete( $request ) {
		$post_id = $request->get_param( 'post_id' );

		delete_post_meta( $post_id, AIInit::META_KEY );
		delete_post_meta( $post_id, AIInit::TYPE_META_KEY );
		delete_post_meta( $post_id, AIInit::CONFIDENCE_META_KEY );
		delete_post_meta( $post_id, '_rtrs_faqpage_data' );
		wp_cache_delete( "rtrs_ai_schemas_{$post_id}", 'rtrs_ai' );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Schema data deleted.', 'review-schema' ),
			]
		);
	}

	public function handle_preview( $request ) {
		$post_id         = $request->get_param( 'post_id' );
		$content_schemas = AIInit::normalizeSchemaData( get_post_meta( $post_id, AIInit::META_KEY, true ) );

		// Merge with global schemas so the editor shows the full graph.
		$full_graph = [];
		$validation = null;
		$evaluation = null;

		if ( ! empty( $content_schemas ) ) {
			$renderer       = new SchemaRenderer();
			$global_schemas = $renderer->build_global_schemas( $post_id, $content_schemas );
			$full_graph     = array_merge( $content_schemas, $global_schemas );
			$full_graph     = apply_filters( 'rtrs_ai_schema_before_render', $full_graph, $post_id );

			$validator  = new SchemaValidator();
			$validation = $validator->validate( $full_graph );

			$evaluation = apply_filters( 'rtrs_ai_schema_evaluation', self::get_dummy_evaluation(), $full_graph );
		}

		return rest_ensure_response(
			[
				'schemas'    => $content_schemas,
				'full_graph' => $full_graph,
				'type'       => get_post_meta( $post_id, AIInit::TYPE_META_KEY, true ) ?: '',
				'confidence' => (int) get_post_meta( $post_id, AIInit::CONFIDENCE_META_KEY, true ),
				'validation' => $validation,
				'evaluation' => $evaluation,
			]
		);
	}

	/**
	 * Save FAQPage mainEntity data to dedicated FAQ meta.
	 *
	 * @param int   $post_id          Post ID.
	 * @param array $faq_main_entity  mainEntity array from FAQPage schema.
	 */
	private function save_faq_main_entity_to_meta( $post_id, $faq_main_entity ) {
		$entities = isset( $faq_main_entity['@type'] )
			? [ $faq_main_entity ]
			: $faq_main_entity;

		$faq_data = [];
		foreach ( $entities as $entity ) {
			if ( ( $entity['@type'] ?? '' ) === 'Question'
				&& ! empty( $entity['name'] )
				&& ! empty( $entity['acceptedAnswer']['text'] )
			) {
				$faq_data[] = [
					'question' => sanitize_text_field( $entity['name'] ),
					'answer'   => sanitize_textarea_field( $entity['acceptedAnswer']['text'] ),
				];
			}
		}

		if ( ! empty( $faq_data ) ) {
			update_post_meta( $post_id, '_rtrs_faqpage_data', $faq_data );
		}
	}

	/**
	 * Build a FAQPage schema node from existing _rtrs_faqpage_data meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null FAQPage schema or null if no valid data.
	 */
	public static function build_faqpage_from_meta( $post_id ) {
		$faq_data = get_post_meta( $post_id, '_rtrs_faqpage_data', true );

		if ( empty( $faq_data ) || ! is_array( $faq_data ) ) {
			return null;
		}

		$questions = [];
		foreach ( $faq_data as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}

			$questions[] = [
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $item['answer'],
				],
			];
		}

		if ( empty( $questions ) ) {
			return null;
		}

		$permalink = trailingslashit( get_permalink( $post_id ) );

		return [
			'@type'      => 'FAQPage',
			'@id'        => $permalink . '#faqpage',
			'mainEntity' => $questions,
		];
	}

}
