<?php
/**
 * Media Schema Linker.
 *
 * Links VideoObject and AudioObject schemas to Product and SoftwareApplication
 * entries in the graph via the subjectOf property.
 *
 * @package Rtrs\Modules\Schema\Hooks
 */

namespace Rtrs\Modules\Schema\Hooks;

use Rtrs\Modules\Schema\Helpers\SchemaFns;
use Rtrs\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MediaSchemaLinker
 *
 * Hooks into schema graph pipelines to add subjectOf references
 * from Product/SoftwareApplication to VideoObject/AudioObject.
 */
class MediaSchemaLinker {

	use SingletonTrait;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function __instance() {
		add_filter( 'rtrs_schema_graph_data', [ $this, 'link_media_to_graph' ], 25 );
		add_filter( 'rtrs_ai_schema_before_render', [ $this, 'link_media_to_graph' ], 25 );
	}

	/**
	 * Link media schemas to Product/SoftwareApplication in the graph.
	 *
	 * @param array $schema_graph_list The schema graph list.
	 *
	 * @return array Modified schema graph list.
	 */
	public function link_media_to_graph( $schema_graph_list ) {
		return SchemaFns::link_media_to_graph( $schema_graph_list );
	}
}
