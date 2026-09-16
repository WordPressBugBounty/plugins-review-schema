<?php
/**
 * Persistence for "Verify with AI" channel results.
 *
 * A channel verification (bounded AI re-score) is produced on demand by the Pro
 * ChannelAiVerifier and is otherwise ephemeral. This store persists the verified
 * score/grade/notes to post meta, keyed by a signature of the deterministic
 * channel. On a later render the verified result is re-applied only while the
 * signature still matches — i.e. until the post content (or any input that moves
 * the channel score) changes, after which the deterministic score is shown again
 * and the channel can be re-verified.
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

use Rtrs\Modules\Seo\Analysis\Grade;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AiVerifyStore
 */
class AiVerifyStore {

	/**
	 * Post-meta key prefix; the channel key ('seo'|'aeo'|'geo') is appended.
	 */
	const META_PREFIX = '_rtrs_ai_verify_';

	/**
	 * Fingerprint of a deterministic channel.
	 *
	 * Combines the aggregate score with each criterion's key => value so the
	 * signature changes whenever any scored input changes (content, links,
	 * headings, SEO title/meta, …). Optional criteria are intentionally ignored
	 * since they never affect the score.
	 *
	 * @param array $channel Channel object.
	 * @return string
	 */
	public static function signature( array $channel ) {
		$parts = [
			'score'    => isset( $channel['score'] ) ? (int) $channel['score'] : 0,
			'criteria' => [],
		];

		if ( ! empty( $channel['criteria'] ) && is_array( $channel['criteria'] ) ) {
			foreach ( $channel['criteria'] as $criterion ) {
				$key = isset( $criterion['key'] ) ? (string) $criterion['key'] : '';
				$parts['criteria'][ $key ] = isset( $criterion['value'] ) ? (int) $criterion['value'] : 0;
			}
		}

		return md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * Persist a verified channel, fingerprinted against its deterministic form.
	 *
	 * @param string $key           Channel key ('seo'|'aeo'|'geo').
	 * @param int    $post_id       Post ID.
	 * @param array  $deterministic The channel before AI verification.
	 * @param array  $verified      The channel after AI verification.
	 * @return void
	 */
	public static function save( $key, $post_id, array $deterministic, array $verified ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || empty( $verified['scored_by'] ) || 'ai' !== $verified['scored_by'] ) {
			return;
		}

		update_post_meta(
			$post_id,
			self::META_PREFIX . $key,
			[
				'score'  => isset( $verified['score'] ) ? (int) $verified['score'] : 0,
				'grade'  => isset( $verified['grade'] ) ? (string) $verified['grade'] : '',
				'notes'  => self::collect_criteria_notes( $verified ),
				'values' => self::collect_criteria_values( $verified ),
				'sig'    => self::signature( $deterministic ),
				'time'   => time(),
			]
		);
	}

	/**
	 * Build a `criterion key => notes[]` map from a verified channel.
	 *
	 * @param array $verified The channel after AI verification.
	 * @return array<string, array>
	 */
	private static function collect_criteria_notes( array $verified ) {
		$map = [];

		if ( empty( $verified['criteria'] ) || ! is_array( $verified['criteria'] ) ) {
			return $map;
		}

		foreach ( $verified['criteria'] as $criterion ) {
			$key = isset( $criterion['key'] ) ? (string) $criterion['key'] : '';
			if ( '' === $key || empty( $criterion['ai_notes'] ) || ! is_array( $criterion['ai_notes'] ) ) {
				continue;
			}
			$map[ $key ] = $criterion['ai_notes'];
		}

		return $map;
	}

	/**
	 * Build a `criterion key => AI-adjusted value` map from a verified channel.
	 *
	 * Persists the per-criterion percentages the AI shifted so a restored
	 * verification shows the same numbers (and derived status) it did when the
	 * review ran, not the deterministic values.
	 *
	 * @param array $verified The channel after AI verification.
	 * @return array<string, int>
	 */
	private static function collect_criteria_values( array $verified ) {
		$map = [];

		if ( empty( $verified['criteria'] ) || ! is_array( $verified['criteria'] ) ) {
			return $map;
		}

		foreach ( $verified['criteria'] as $criterion ) {
			$key = isset( $criterion['key'] ) ? (string) $criterion['key'] : '';
			if ( '' === $key || ! empty( $criterion['locked'] ) ) {
				continue;
			}
			$map[ $key ] = max( 0, min( 100, isset( $criterion['value'] ) ? (int) $criterion['value'] : 0 ) );
		}

		return $map;
	}

	/**
	 * Delete a saved AI verification so the channel reverts to its deterministic
	 * score. Used by the editor's "Remove AI review" action.
	 *
	 * @param string $key     Channel key ('seo'|'aeo'|'geo').
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function clear( $key, $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		delete_post_meta( $post_id, self::META_PREFIX . $key );
	}

	/**
	 * Re-apply a saved verification onto a freshly built deterministic channel.
	 *
	 * Returns the channel unchanged when there is no saved result or the saved
	 * signature no longer matches (the content changed) — so the deterministic
	 * score is shown and the channel can be re-verified.
	 *
	 * @param string $key     Channel key ('seo'|'aeo'|'geo').
	 * @param int    $post_id Post ID.
	 * @param array  $channel Freshly built deterministic channel.
	 * @return array
	 */
	public static function restore( $key, $post_id, array $channel ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return $channel;
		}

		$saved = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		if ( ! is_array( $saved ) || empty( $saved['sig'] ) ) {
			return $channel;
		}

		if ( $saved['sig'] !== self::signature( $channel ) ) {
			return $channel; // Content changed — fall back to the deterministic score.
		}

		$channel['score']     = max( 0, min( 100, isset( $saved['score'] ) ? (int) $saved['score'] : 0 ) );
		$channel['grade']     = isset( $saved['grade'] ) ? (string) $saved['grade'] : ( isset( $channel['grade'] ) ? $channel['grade'] : '' );
		$channel['scored_by'] = 'ai';

		// Re-apply the saved per-criterion adjusted values (and derived status)
		// plus notes, keyed by criterion key.
		$notes_map  = isset( $saved['notes'] ) && is_array( $saved['notes'] ) ? $saved['notes'] : [];
		$values_map = isset( $saved['values'] ) && is_array( $saved['values'] ) ? $saved['values'] : [];
		if ( ! empty( $channel['criteria'] ) && is_array( $channel['criteria'] ) ) {
			foreach ( $channel['criteria'] as $index => $criterion ) {
				$ckey = isset( $criterion['key'] ) ? (string) $criterion['key'] : '';

				$channel['criteria'][ $index ]['ai_notes'] = ( '' !== $ckey && isset( $notes_map[ $ckey ] ) && is_array( $notes_map[ $ckey ] ) ) ? $notes_map[ $ckey ] : [];

				if ( '' !== $ckey && empty( $criterion['locked'] ) && isset( $values_map[ $ckey ] ) ) {
					$value                                   = max( 0, min( 100, (int) $values_map[ $ckey ] ) );
					$channel['criteria'][ $index ]['value']  = $value;
					$channel['criteria'][ $index ]['status'] = Grade::status( $value );
				}
			}
		}

		return $channel;
	}
}
