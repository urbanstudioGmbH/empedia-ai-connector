<?php
/**
 * Plugin Name: EMPEDIA AI Connector
 * Plugin URI: https://empedia.cloud/
 * Description: Connects the WordPress AI Client to EMPEDIA for text generation, image understanding, embeddings and text to speech.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: urbanstudio GmbH
 * Author URI: https://www.urbanstudio.de/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: empedia-ai-connector
 *
 * @package Empedia_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Preserve the plugin path when WordPress loads it through a symlink.
if ( ! defined( 'EMPEDIA_AI_CONNECTOR_PLUGIN_DIR' ) ) {
	define( 'EMPEDIA_AI_CONNECTOR_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) );
}

/**
 * Register the provider after WordPress initializes the AI client.
 *
 * @return void
 */
function empedia_ai_register_provider() {
	if ( ! class_exists( '\\WordPress\\AiClient\\AiClient' ) ) {
		return;
	}
	require_once __DIR__ . '/includes/class-empedia-ai-models.php';
	require_once __DIR__ . '/includes/class-empedia-ai-text-model.php';
	if ( interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ) ) {
		require_once __DIR__ . '/includes/class-empedia-ai-embedding-model.php';
	}
	if ( interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\TextToSpeechConversion\\Contracts\\TextToSpeechConversionModelInterface' ) ) {
		require_once __DIR__ . '/includes/class-empedia-ai-tts-model.php';
	}
	require_once __DIR__ . '/includes/class-empedia-ai-provider.php';
	$registry = \WordPress\AiClient\AiClient::defaultRegistry();
	if ( ! $registry->hasProvider( Empedia_AI_Provider::class ) ) {
		$registry->registerProvider( Empedia_AI_Provider::class );
	}
}
add_action( 'init', 'empedia_ai_register_provider', 5 );
