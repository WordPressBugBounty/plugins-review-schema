<?php
/**
 * AI Client - Handles communication with AI APIs (OpenAI / Anthropic Claude / Google Gemini).
 *
 * @package Rtrs\AI
 * @since   1.0.0
 */

namespace Rtrs\AI;

defined( 'ABSPATH' ) || exit;

class AIClient {

	/**
	 * API provider identifier.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * AI model to use for completions.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Maximum tokens for the AI response.
	 *
	 * @var int
	 */
	private $max_tokens;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->provider = AIInit::getSetting( 'api_provider', 'openai' );

		// Read the correct API key and model based on the selected provider.
		switch ( $this->provider ) {
			case 'anthropic':
				$this->api_key = AIInit::getSetting( 'anthropic_api_key', '' );
				$this->model   = AIInit::getSetting( 'anthropic_model', 'claude-haiku-4-5-20251001' );
				break;
			case 'gemini':
				$this->api_key = AIInit::getSetting( 'gemini_api_key', '' );
				$this->model   = AIInit::getSetting( 'gemini_model', 'gemini-2.5-flash' );
				break;
			default:
				$this->api_key = AIInit::getSetting( 'openai_api_key', '' );
				$this->model   = AIInit::getSetting( 'openai_model', 'gpt-4o-mini' );
				break;
		}

		// Backward compatibility: fall back to the old unified 'api_key' setting.
		if ( empty( $this->api_key ) ) {
			$this->api_key = AIInit::getSetting( 'api_key', '' );
		}

		// Backward compatibility: fall back to the old unified 'model' setting.
		if ( empty( $this->model ) ) {
			$this->model = AIInit::getSetting( 'model', 'gpt-4o-mini' );
		}

		$this->max_tokens = (int) AIInit::getSetting( 'max_tokens', 4096 );

		if ( $this->max_tokens < 1 ) {
			$this->max_tokens = 4096;
		}
	}

	/**
	 * Send a prompt to the AI API and get a response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt System-level instructions.
	 * @param string $user_prompt   User-level prompt with content data.
	 *
	 * @return array|\WP_Error Parsed response array or WP_Error on failure.
	 */
	public function complete( $system_prompt, $user_prompt ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'AI API key is not configured.', 'review-schema' ) );
		}

		switch ( $this->provider ) {
			case 'anthropic':
				return $this->call_anthropic( $system_prompt, $user_prompt );
			case 'gemini':
				return $this->call_gemini( $system_prompt, $user_prompt );
			default:
				return $this->call_openai( $system_prompt, $user_prompt );
		}
	}

	/**
	 * Call the OpenAI Chat Completions API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt System instructions.
	 * @param string $user_prompt   User prompt.
	 *
	 * @return array|\WP_Error Parsed JSON response or WP_Error.
	 */
	private function call_openai( $system_prompt, $user_prompt ) {
		$is_o_series = (bool) preg_match( '/^o\d/', $this->model );
		$is_legacy   = (bool) preg_match( '/^gpt-4o/', $this->model );

		$messages = [
			[
				'role'    => $is_o_series ? 'developer' : 'system',
				'content' => $system_prompt,
			],
			[
				'role'    => 'user',
				'content' => $user_prompt,
			],
		];

		$body = [
			'model'           => $this->model,
			'messages'        => $messages,
			'response_format' => [ 'type' => 'json_object' ],
		];

		$body['max_completion_tokens'] = $this->max_tokens;

		if ( $is_legacy ) {
			$body['temperature'] = 0.2;
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 5000,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		return $this->parse_openai_response( $response );
	}

	/**
	 * Call the Anthropic Messages API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt System instructions.
	 * @param string $user_prompt   User prompt.
	 *
	 * @return array|\WP_Error Parsed JSON response or WP_Error.
	 */
	private function call_anthropic( $system_prompt, $user_prompt ) {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 5000,
				'headers' => [
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => '2023-06-01',
				],
				'body'    => wp_json_encode(
					[
						'model'      => $this->model,
						'max_tokens' => $this->max_tokens,
						'system'     => $system_prompt,
						'messages'   => [
							[
								'role'    => 'user',
								'content' => $user_prompt,
							],
						],
					]
				),
			]
		);

		return $this->parse_anthropic_response( $response );
	}

	/**
	 * Parse OpenAI API response and extract JSON content.
	 *
	 * @since 1.0.0
	 *
	 * @param array|\WP_Error $response HTTP response.
	 *
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_openai_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Unknown API error.', 'review-schema' );
			return new \WP_Error( 'api_error', $message );
		}

		if ( null === $body ) {
			return new \WP_Error( 'api_error', __( 'Could not decode API response.', 'review-schema' ) );
		}

		// Handle truncated responses.
		$finish_reason = $body['choices'][0]['finish_reason'] ?? '';
		if ( 'length' === $finish_reason ) {
			return new \WP_Error( 'api_error', __( 'AI response was truncated. Try a simpler schema type.', 'review-schema' ) );
		}

		$content = $body['choices'][0]['message']['content'] ?? '';

		if ( empty( $content ) ) {
			$refusal = $body['choices'][0]['message']['refusal'] ?? '';
			if ( $refusal ) {
				return new \WP_Error( 'api_error', $refusal );
			}

			return new \WP_Error( 'api_error', __( 'AI returned an empty response.', 'review-schema' ) );
		}

		return $this->extract_json( $content );
	}

	/**
	 * Parse Anthropic API response and extract JSON content.
	 *
	 * @since 1.0.0
	 *
	 * @param array|\WP_Error $response HTTP response.
	 *
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_anthropic_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Unknown API error.', 'review-schema' );
			return new \WP_Error( 'api_error', $message );
		}

		$content = '';

		foreach ( ( $body['content'] ?? [] ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$content .= $block['text'];
			}
		}

		return $this->extract_json( $content );
	}

	/**
	 * Call the Google Gemini generateContent API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt System instructions.
	 * @param string $user_prompt   User prompt.
	 *
	 * @return array|\WP_Error Parsed JSON response or WP_Error.
	 */
	private function call_gemini( $system_prompt, $user_prompt ) {
		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $this->model ),
			rawurlencode( $this->api_key )
		);

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 5000,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'contents'          => [
							[
								'role'  => 'user',
								'parts' => [
									[ 'text' => $user_prompt ],
								],
							],
						],
						'systemInstruction' => [
							'parts' => [
								[ 'text' => $system_prompt ],
							],
						],
						'generationConfig'  => [
							'temperature'      => 0.2,
							'maxOutputTokens'  => $this->max_tokens,
							'responseMimeType' => 'application/json',
						],
					]
				),
			]
		);

		return $this->parse_gemini_response( $response );
	}

	/**
	 * Parse Google Gemini API response and extract JSON content.
	 *
	 * @since 1.0.0
	 *
	 * @param array|\WP_Error $response HTTP response.
	 *
	 * @return array|\WP_Error Parsed data or WP_Error.
	 */
	private function parse_gemini_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Unknown API error.', 'review-schema' );
			return new \WP_Error( 'api_error', $message );
		}

		$content = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

		return $this->extract_json( $content );
	}

	/**
	 * Extract JSON from AI response text, handling markdown code fences.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw AI response text.
	 *
	 * @return array|\WP_Error Decoded JSON array or WP_Error.
	 */
	private function extract_json( $text ) {
		/** Strip markdown code fences if present. */
		$text = preg_replace( '/```(?:json)?\s*/i', '', $text );
		$text = trim( $text );

		/**
		 * Sanitize control characters that break JSON parsing.
		 * Replace raw tabs/newlines/carriage returns inside string values
		 * with their escaped equivalents, and strip other control chars.
		 */
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );

		/** Try direct parse first (fastest path). */
		$decoded = json_decode( $text, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $decoded;
		}

		/**
		 * Fallback: extract the outermost JSON object from the response.
		 * Handles leading/trailing text the AI may have added.
		 */
		$first_brace = strpos( $text, '{' );
		$last_brace  = strrpos( $text, '}' );

		if ( false !== $first_brace && false !== $last_brace && $last_brace > $first_brace ) {
			$json_str = substr( $text, $first_brace, $last_brace - $first_brace + 1 );

			// Remove trailing commas before } or ] (common AI mistake).
			$json_str = preg_replace( '/,\s*([\}\]])/', '$1', $json_str );

			$decoded = json_decode( $json_str, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}

		return new \WP_Error(
			'json_parse_error',
			sprintf(
				/* translators: %s: JSON error message */
				__( 'Failed to parse AI response: %s', 'review-schema' ),
				json_last_error_msg()
			)
		);
	}
}
