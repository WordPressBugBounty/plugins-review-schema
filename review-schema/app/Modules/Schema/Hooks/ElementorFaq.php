<?php
/**
 * Elementor FAQ Schema Support.
 *
 * Adds FAQPage structured data for Elementor accordion and nested-accordion widgets.
 *
 * @package review-schema
 * @since   1.2.0
 */

namespace Rtrs\Modules\Schema\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Rtrs\Helpers\Functions;
use Rtrs\Modules\Schema\Helpers\SchemaFns;
use Rtrs\Traits\SingletonTrait;

class ElementorFaq {

	use SingletonTrait;

	private function __instance() {
		if ( ! Functions::is_plugin_active( 'elementor/elementor.php' ) ) {
			return;
		}

		add_action( 'elementor/element/accordion/section_title/before_section_end', [ $this, 'add_faq_control' ], 99 );
		add_action( 'elementor/element/nested-accordion/section_items/before_section_end', [ $this, 'add_faq_control' ], 99 );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'add_faq_schema' ], 15, 2 );
		add_filter( 'rtrs_schema_graph_data', [ $this, 'link_faq_to_webpage' ], 20 );
	}

	/**
	 * Store the FAQ @id when FAQ schema is generated.
	 *
	 * @var string|null
	 */
	private $faq_schema_id = null;

	/**
	 * Add FAQ schema toggle control to accordion widget settings.
	 *
	 * @param \Elementor\Widget_Base $widget The widget instance.
	 */
	public function add_faq_control( $widget ) {
		$widget->add_control(
			'rtrs_add_faq_schema',
			[
				'label'        => esc_html__( 'Enable FAQ Schema', 'review-schema' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'review-schema' ),
				'label_off'    => esc_html__( 'No', 'review-schema' ),
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'description'  => sprintf(
					/* translators: %s: plugin name */
					esc_html__( 'Output FAQPage structured data from this accordion. Added by %s plugin.', 'review-schema' ),
					'<strong style="color:#0028ff;">SchemaEngine AI</strong>'
				),
			]
		);
	}

	/**
	 * Add FAQPage schema to the graph data on the frontend.
	 *
	 * @param array $schema_graph_list Existing schema graph list.
	 * @param int   $post_id           Current post ID.
	 * @return array Modified schema graph list.
	 */
	public function add_faq_schema( $schema_graph_list, $post_id ) {
		if ( ! is_singular() && ! is_admin() && ! wp_doing_ajax() ) {
			return $schema_graph_list;
		}

		if ( ! Functions::is_plugin_active( 'elementor/elementor.php' ) ) {
			return $schema_graph_list;
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return $schema_graph_list;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return $schema_graph_list;
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			return $schema_graph_list;
		}

		$elements = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( empty( $elements ) || ! is_array( $elements ) ) {
			return $schema_graph_list;
		}

		$faqs = $this->get_accordion_faqs( $elements );
		if ( empty( $faqs ) ) {
			return $schema_graph_list;
		}

		$main_entity = [];
		foreach ( $faqs as $faq ) {
			$question = wp_strip_all_tags( $faq['question'] );
			$answer   = wp_strip_all_tags( $faq['answer'] );

			if ( empty( $question ) || empty( $answer ) ) {
				continue;
			}

			$main_entity[] = [
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $answer,
				],
			];
		}

		if ( ! empty( $main_entity ) ) {
			$faq_id              = get_permalink( $post_id ) . '#faq';
			$this->faq_schema_id = $faq_id;

			$schema_graph_list[] = [
				'@type'      => 'FAQPage',
				'@id'        => $faq_id,
				'url'        => get_permalink( $post_id ),
				'mainEntity' => $main_entity,
			];
		}

		return $schema_graph_list;
	}
	/**
	 * Link the FAQPage to the main content schema and WebPage.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 * @return array Modified schema graph list.
	 */
	public function link_faq_to_webpage( $schema_graph_list ) {
		if ( ! is_singular() && ! is_admin() && ! wp_doing_ajax() ) {
			return $schema_graph_list;
		}

		return SchemaFns::link_faq_to_graph( $schema_graph_list, $this->faq_schema_id );
	}

	/**
	 * Recursively extract FAQ pairs from Elementor elements.
	 *
	 * @param array $elements Elementor element tree.
	 * @return array Array of ['question' => ..., 'answer' => ...].
	 */
	private function get_accordion_faqs( $elements ) {
		$faqs = [];

		foreach ( $elements as $element ) {
			$widget_type = ! empty( $element['widgetType'] ) ? $element['widgetType'] : '';
			$settings    = ! empty( $element['settings'] ) ? $element['settings'] : [];
			$faq_enabled = ! empty( $settings['rtrs_add_faq_schema'] ) && 'yes' === $settings['rtrs_add_faq_schema'];

			if ( 'accordion' === $widget_type && $faq_enabled ) {
				$tabs = ! empty( $settings['tabs'] ) ? $settings['tabs'] : [];
				foreach ( $tabs as $tab ) {
					$question = ! empty( $tab['tab_title'] ) ? $tab['tab_title'] : '';
					$answer   = ! empty( $tab['tab_content'] ) ? $tab['tab_content'] : '';
					if ( $question && $answer ) {
						$faqs[] = [
							'question' => $question,
							'answer'   => $answer,
						];
					}
				}
			} elseif ( 'nested-accordion' === $widget_type && $faq_enabled ) {
				$items = ! empty( $settings['items'] ) ? $settings['items'] : [];
				foreach ( $items as $index => $item ) {
					$question = ! empty( $item['item_title'] ) ? $item['item_title'] : '';
					$answer   = $this->render_nested_content( $element, $index );
					if ( $question && $answer ) {
						$faqs[] = [
							'question' => $question,
							'answer'   => $answer,
						];
					}
				}
			}

			// Recurse into child elements.
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$faqs = array_merge( $faqs, $this->get_accordion_faqs( $element['elements'] ) );
			}
		}

		return $faqs;
	}

	/**
	 * Render the content of a nested accordion item.
	 *
	 * @param array $element The nested-accordion element data.
	 * @param int   $index   The item index.
	 * @return string Rendered HTML content.
	 */
	private function render_nested_content( $element, $index ) {
		$children = ! empty( $element['elements'] ) ? $element['elements'] : [];
		if ( ! isset( $children[ $index ] ) ) {
			return '';
		}

		$child   = $children[ $index ];
		$content = '';

		if ( ! empty( $child['elements'] ) && is_array( $child['elements'] ) ) {
			foreach ( $child['elements'] as $inner ) {
				$content .= $this->render_element_content( $inner );
			}
		}

		return $content;
	}

	/**
	 * Recursively render element content to HTML string.
	 *
	 * @param array $element Elementor element data.
	 * @return string Rendered content.
	 */
	private function render_element_content( $element ) {
		$content     = '';
		$widget_type = ! empty( $element['widgetType'] ) ? $element['widgetType'] : '';
		$settings    = ! empty( $element['settings'] ) ? $element['settings'] : [];

		if ( 'text-editor' === $widget_type && ! empty( $settings['editor'] ) ) {
			$content .= $settings['editor'];
		} elseif ( 'heading' === $widget_type && ! empty( $settings['title'] ) ) {
			$content .= '<p>' . $settings['title'] . '</p>';
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child ) {
				$content .= $this->render_element_content( $child );
			}
		}

		return $content;
	}

	/**
	 * Generate a random Elementor-style element ID.
	 *
	 * @return string 7-character hex string.
	 */
	private static function generate_element_id() {
		return substr( md5( wp_generate_uuid4() ), 0, 7 );
	}

	/**
	 * Check whether the given post is built with Elementor.
	 *
	 * Uses post meta check instead of Elementor's document API to ensure
	 * reliable detection during REST API requests where Elementor's document
	 * manager may not be fully initialized.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_elementor_post( $post_id ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'elementor/elementor.php' ) ) {
			return false;
		}

		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Append a nested-accordion Elementor widget with FAQ data to the page.
	 *
	 * Creates a nested-accordion widget populated with the given question/answer
	 * pairs and enables the "Enable FAQ Schema" toggle. The widget is appended
	 * inside a new top-level container at the end of the Elementor data.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $faq_data Array of ['question' => ..., 'answer' => ...].
	 * @return bool True on success, false on failure.
	 */
	public static function append_faq_widget( $post_id, $faq_data ) {
		if ( empty( $faq_data ) || ! is_array( $faq_data ) ) {
			return false;
		}

		if ( ! self::is_elementor_post( $post_id ) ) {
			return false;
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			return false;
		}

		$elements = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( empty( $elements ) || ! is_array( $elements ) ) {
			return false;
		}

		// Check if a nested-accordion with FAQ schema enabled already exists.
		if ( self::has_faq_accordion( $elements ) ) {
			return false;
		}

		// Build nested-accordion widget structure.
		$items         = [];
		$item_children = [];

		foreach ( $faq_data as $faq ) {
			$question = $faq['question'] ?? '';
			$answer   = $faq['answer'] ?? '';

			if ( empty( $question ) || empty( $answer ) ) {
				continue;
			}

			$items[] = [
				'item_title' => $question,
				'_id'        => self::generate_element_id(),
			];

			$item_children[] = [
				'id'       => self::generate_element_id(),
				'elType'   => 'container',
				'settings' => [],
				'elements' => [
					[
						'id'         => self::generate_element_id(),
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => [
							'editor' => wp_kses_post( $answer ),
						],
						'elements'   => [],
					],
				],
			];
		}

		if ( empty( $items ) ) {
			return false;
		}

		$accordion_widget = [
			'id'         => self::generate_element_id(),
			'elType'     => 'widget',
			'widgetType' => 'nested-accordion',
			'settings'   => [
				'items'              => $items,
				'rtrs_add_faq_schema' => 'yes',
			],
			'elements'   => $item_children,
		];

		// Wrap in a top-level container.
		$container = [
			'id'       => self::generate_element_id(),
			'elType'   => 'container',
			'settings' => [],
			'elements' => [ $accordion_widget ],
		];

		$elements[] = $container;

		update_post_meta(
			$post_id,
			'_elementor_data',
			wp_slash( wp_json_encode( $elements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
		);

		// Clear Elementor CSS cache so the new widget renders on frontend.
		if ( class_exists( '\Elementor\Plugin' ) && ! empty( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return true;
	}

	/**
	 * Check whether a given post has an Elementor FAQ accordion widget.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_elementor_faq( $post_id ) {
		if ( ! self::is_elementor_post( $post_id ) ) {
			return false;
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) ) {
			return false;
		}

		$elements = is_string( $elementor_data ) ? json_decode( $elementor_data, true ) : $elementor_data;
		if ( empty( $elements ) || ! is_array( $elements ) ) {
			return false;
		}

		return self::has_faq_accordion( $elements );
	}

	/**
	 * Recursively check if a nested-accordion with FAQ schema enabled exists.
	 *
	 * @param array $elements Elementor element tree.
	 * @return bool
	 */
	private static function has_faq_accordion( $elements ) {
		foreach ( $elements as $element ) {
			$widget_type = $element['widgetType'] ?? '';
			$settings    = $element['settings'] ?? [];

			if ( 'nested-accordion' === $widget_type
				&& ! empty( $settings['rtrs_add_faq_schema'] )
				&& 'yes' === $settings['rtrs_add_faq_schema']
			) {
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::has_faq_accordion( $element['elements'] ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
