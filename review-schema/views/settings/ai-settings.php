<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rtrs_options = [
	'api_section'          => [
		'title' => esc_html__( 'API Configuration', 'review-schema' ),
		'type'  => 'title',
	],
	'api_provider'         => [
		'title'       => esc_html__( 'API Provider', 'review-schema' ),
		'type'        => 'select',
		'default'     => 'openai',
		'options'     => [
			'openai'    => esc_html__( 'OpenAI', 'review-schema' ),
			'anthropic' => esc_html__( 'Anthropic (Claude)', 'review-schema' ),
			'gemini'    => esc_html__( 'Google Gemini', 'review-schema' ),
		],
		'description' => esc_html__( 'Select the AI provider for schema generation.', 'review-schema' ),
	],
	'openai_api_key'       => [
		'title'       => esc_html__( 'OpenAI API Key', 'review-schema' ),
		'type'        => 'password',
		'default'     => '',
		'class'       => 'regular-text',
		'description' => esc_html__( 'Enter your OpenAI API key.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'openai',
					'condition' => '=',
				],
			],
		],
	],
	'openai_model'         => [
		'title'       => esc_html__( 'OpenAI Model', 'review-schema' ),
		'type'        => 'select',
		'default'     => 'gpt-4.1',
		'options'     => [
			'gpt-4o-mini'  => esc_html__( 'GPT-4o Mini', 'review-schema' ),
			'gpt-4o'       => esc_html__( 'GPT-4o', 'review-schema' ),
			'gpt-4.1-nano' => esc_html__( 'GPT-4.1 Nano', 'review-schema' ),
			'gpt-4.1-mini' => esc_html__( 'GPT-4.1 Mini', 'review-schema' ),
			'gpt-4.1'      => esc_html__( 'GPT-4.1 (Recommended)', 'review-schema' ),
			'gpt-5-mini'   => esc_html__( 'GPT-5 Mini', 'review-schema' ),
			'gpt-5'        => esc_html__( 'GPT-5', 'review-schema' ),
		],
		'description' => esc_html__( 'Select the OpenAI model to use.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'openai',
					'condition' => '=',
				],
			],
		],
	],
	'anthropic_api_key'    => [
		'title'       => esc_html__( 'Anthropic API Key', 'review-schema' ),
		'type'        => 'password',
		'default'     => '',
		'class'       => 'regular-text',
		'description' => esc_html__( 'Enter your Anthropic API key.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'anthropic',
					'condition' => '=',
				],
			],
		],
	],
	'anthropic_model'      => [
		'title'       => esc_html__( 'Anthropic Model', 'review-schema' ),
		'type'        => 'select',
		'default'     => 'claude-haiku-4-5-20251001',
		'options'     => [
			'claude-haiku-4-5-20251001'  => esc_html__( 'Claude Haiku 4.5', 'review-schema' ),
			'claude-sonnet-4-5-20250929' => esc_html__( 'Claude Sonnet 4.5', 'review-schema' ),
			'claude-sonnet-4-6'          => esc_html__( 'Claude Sonnet 4.6', 'review-schema' ),
			'claude-opus-4-6'            => esc_html__( 'Claude Opus 4.6 (Recommended)', 'review-schema' ),
		],
		'description' => esc_html__( 'Select the Anthropic model to use.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'anthropic',
					'condition' => '=',
				],
			],
		],
	],
	'gemini_api_key'       => [
		'title'       => esc_html__( 'Gemini API Key', 'review-schema' ),
		'type'        => 'password',
		'default'     => '',
		'class'       => 'regular-text',
		'description' => esc_html__( 'Enter your Google Gemini API key.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'gemini',
					'condition' => '=',
				],
			],
		],
	],
	'gemini_model'         => [
		'title'       => esc_html__( 'Gemini Model', 'review-schema' ),
		'type'        => 'select',
		'default'     => 'gemini-2.5-flash',
		'options'     => [
			'gemini-2.5-flash-lite'  => esc_html__( 'Gemini 2.5 Flash Lite', 'review-schema' ),
			'gemini-2.5-flash'       => esc_html__( 'Gemini 2.5 Flash (Recommended)', 'review-schema' ),
			'gemini-2.5-pro'         => esc_html__( 'Gemini 2.5 Pro', 'review-schema' ),
			'gemini-3-flash-preview' => esc_html__( 'Gemini 3 Flash', 'review-schema' ),
			'gemini-3.1-pro-preview' => esc_html__( 'Gemini 3.1 Pro', 'review-schema' ),
		],
		'description' => esc_html__( 'Select the Google Gemini model to use.', 'review-schema' ),
		'depends'     => [
			'on' => [
				[
					'field'     => 'rtrs_ai_settings.api_provider',
					'value'     => 'gemini',
					'condition' => '=',
				],
			],
		],
	],
	'max_tokens'           => [
		'title'       => esc_html__( 'Max Tokens', 'review-schema' ),
		'type'        => 'number',
		'default'     => 4096,
		'class'       => 'small-text',
		'description' => esc_html__( 'Maximum number of tokens for the AI response. Higher values allow longer/more detailed schema output but cost more. Default: 4096.', 'review-schema' ),
	],

	'general_section'      => [
		'title' => esc_html__( 'General AI Settings', 'review-schema' ),
		'type'  => 'title',
	],
	'ai_enabled'           => [
		'title'       => esc_html__( 'Enable AI', 'review-schema' ),
		'label'       => esc_html__( 'Allow', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'description' => esc_html__( 'Enable AI to generate schema.', 'review-schema' ),
	],
	'confidence_threshold' => [
		'title'       => esc_html__( 'Confidence Threshold', 'review-schema' ),
		'type'        => 'number',
		'default'     => 60,
		'class'       => 'small-text',
		'description' => esc_html__( 'If AI confidence is below this threshold (0-100), it will fallback to generic WebPage schema.', 'review-schema' ),
		// Visible only when global Schema output AND AI are both enabled.
		'depends'     => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_general_settings.schema_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.ai_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'faq_count'            => [
		'title'       => esc_html__( 'FAQ Count', 'review-schema' ),
		'type'        => 'number',
		'default'     => 5,
		'class'       => 'small-text',
		'description' => esc_html__( 'Number of FAQs to auto-generate for FAQ content (also applies to FAQPage schema when enabled).', 'review-schema' ),
		// Visible only when global Schema output AND AI are both enabled.
		'depends'     => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_general_settings.schema_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.ai_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'auto_generate'        => [
		'title'       => esc_html__( 'Auto-Generate', 'review-schema' ),
		'label'       => esc_html__( 'Enable', 'review-schema' ),
		'type'        => 'checkbox',
		'default'     => '',
		'is_pro'      => true,
		'description' => esc_html__( 'Automatically generate schema with AI based on site info > schema type settings.', 'review-schema' ),
		// Visible only when global Schema output AND AI are both enabled.
		'depends'     => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_general_settings.schema_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.ai_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'reset_batch'          => [
		'title'        => esc_html__( 'Reset Auto-Generation', 'review-schema' ),
		'type'         => 'html',
		'is_pro'       => true,
		'html_content' => '<a style="font-size: 14px;" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=review-schema&tab=ai&rtrs_reset_batch=1' ), 'rtrs_reset_ai_batch' ) . '#/ai' ) . '">'
			. esc_html__( 'Reset & Re-run Auto-Generation', 'review-schema' )
			. '</a><p class="description" style="font-size: 14px;">'
			. esc_html__( 'Re-runs AI schema generation for every eligible published post that does not yet have an AI-generated schema. This clears the batch completion flag, cancels any scheduled run, and starts a fresh background batch via WP-Cron (about one post every 30 seconds). Posts that already have a saved AI schema are skipped — delete the schema from the post first if you want to regenerate it.', 'review-schema' )
			. '</p><p class="description" style="font-size: 14px;"><a target="_blank" rel="noopener noreferrer" href="'
			. esc_url( 'https://schemaengineai.com/docs/docs/ai-settings/' )
			. '">' . esc_html__( 'Learn more about AI auto-generation →', 'review-schema' ) . '</a></p>',
		// Visible only when Schema output, AI, and Auto-Generate are all enabled.
		'depends'      => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_general_settings.schema_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.ai_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.auto_generate',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],
	'auto_gen_progress'    => [
		'title'   => esc_html__( 'Auto-Generation Progress', 'review-schema' ),
		'type'    => 'auto_gen_progress',
		'is_pro'  => true,
		// Visible only when Schema output, AI, and Auto-Generate are all enabled.
		'depends' => [
			'relation' => 'and',
			'on'       => [
				[
					'field'     => 'rtrs_general_settings.schema_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.ai_enabled',
					'value'     => 'yes',
					'condition' => '=',
				],
				[
					'field'     => 'rtrs_ai_settings.auto_generate',
					'value'     => 'yes',
					'condition' => '=',
				],
			],
		],
	],

];

return apply_filters( 'rtrs_ai_settings_options', $rtrs_options );
