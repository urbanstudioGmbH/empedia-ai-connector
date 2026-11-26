<?php
/**
 * EMPEDIA provider implementation.
 *
 * @package Empedia_AI_Connector
 */

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider for EMPEDIA's text, embedding and speech APIs.
 */
class Empedia_AI_Provider extends AbstractApiProvider {
	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected static function baseUrl(): string {
		return 'https://llmapi.empedia.cloud/v1';
	}
	/**
	 * {@inheritDoc}
	 *
	 * @return ProviderMetadata
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$plugin_dir = defined( 'EMPEDIA_AI_CONNECTOR_PLUGIN_DIR' ) ? EMPEDIA_AI_CONNECTOR_PLUGIN_DIR : dirname( __DIR__ );
		return new ProviderMetadata(
			'empedia',
			'EMPEDIA',
			ProviderTypeEnum::cloud(),
			'https://empedia.cloud/',
			RequestAuthenticationMethod::apiKey(),
			function_exists( '__' ) ? __( 'Text, image understanding, embeddings and speech from EMPEDIA.', 'empedia-ai-connector' ) : 'Text, image understanding, embeddings and speech from EMPEDIA.',
			$plugin_dir . '/assets/empedia-icon.png'
		);
	}
	/**
	 * {@inheritDoc}
	 *
	 * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
	 */
	protected static function createProviderAvailability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::modelMetadataDirectory() );
	}
	/**
	 * {@inheritDoc}
	 *
	 * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
		return new Empedia_AI_Models();
	}
	/**
	 * Create the model class matching the model's capability.
	 *
	 * @param ModelMetadata    $modelMetadata Model metadata.
	 * @param ProviderMetadata $providerMetadata Provider metadata.
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
	 */
	protected static function createModel( ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata ): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK method signature.
		foreach ( $modelMetadata->getSupportedCapabilities() as $capability ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK method signature.
			if ( $capability->isEmbeddingGeneration() && class_exists( 'Empedia_AI_Embedding_Model' ) ) {
				return new Empedia_AI_Embedding_Model( $modelMetadata, $providerMetadata ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK method signature.
			}
			if ( $capability->isTextToSpeechConversion() && class_exists( 'Empedia_AI_Tts_Model' ) ) {
				return new Empedia_AI_Tts_Model( $modelMetadata, $providerMetadata ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK method signature.
			}
		}
		return new Empedia_AI_Text_Model( $modelMetadata, $providerMetadata ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK method signature.
	}
}
