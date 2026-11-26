<?php
/**
 * Model discovery for EMPEDIA.
 *
 * @package Empedia_AI_Connector
 */

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the models granted to the key, typed as text, embedding or speech models.
 */
class Empedia_AI_Models implements ModelMetadataDirectoryInterface, WithHttpTransporterInterface, WithRequestAuthenticationInterface {
	use WithHttpTransporterTrait;
	use WithRequestAuthenticationTrait;

	/**
	 * Display names of known models.
	 */
	const NAMES = array(
		'google/gemma-4-26B-A4B-it'  => 'Gemma 4 26B A4B',
		'Qwen/Qwen3-VL-Embedding-8B' => 'Qwen3 VL Embedding 8B',
		'empedia/tts'                => 'EMPEDIA Speech',
	);

	/**
	 * Text models that accept images even if the catalogue does not say so.
	 */
	const VISION_MODELS = array( 'google/gemma-4-26B-A4B-it' );

	/**
	 * Catalogue of this request cycle; errors are never cached.
	 *
	 * @var ModelMetadata[]|null
	 */
	private $models_cache = null;

	/**
	 * Retrieve the account-specific model catalogue.
	 *
	 * @return array
	 * @throws RuntimeException If the response is invalid.
	 */
	public function listModelMetadata(): array {
		if ( null !== $this->models_cache ) {
			return $this->models_cache;
		}
		$options = new RequestOptions();
		$options->setMaxRedirects( 0 );
		$request  = new Request( HttpMethodEnum::GET(), Empedia_AI_Provider::url( 'models' ), array(), null, $options );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		$data = $response->getData();
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			throw new RuntimeException( 'EMPEDIA returned an invalid model catalogue.' );
		}
		$models = array();
		foreach ( $data['data'] as $entry ) {
			$model = is_array( $entry ) ? self::metadata_of( $entry ) : null;
			if ( null !== $model ) {
				$models[] = $model;
			}
		}
		$this->models_cache = $models;
		return $models;
	}

	/**
	 * Map a catalogue entry to SDK metadata; unsupported models return null.
	 *
	 * @param array $entry Catalogue entry.
	 * @return ModelMetadata|null
	 */
	private static function metadata_of( array $entry ) {
		$id = isset( $entry['id'] ) && is_string( $entry['id'] ) ? $entry['id'] : '';
		if ( '' === $id || ( array_key_exists( 'is_ready', $entry ) && false === $entry['is_ready'] ) ) {
			return null;
		}
		$type      = isset( $entry['model_type'] ) && is_string( $entry['model_type'] ) ? strtolower( $entry['model_type'] ) : '';
		$endpoints = isset( $entry['supported_endpoints'] ) && is_array( $entry['supported_endpoints'] ) ? $entry['supported_endpoints'] : array();
		$inputs    = isset( $entry['input_modalities'] ) && is_array( $entry['input_modalities'] ) ? $entry['input_modalities'] : array();
		$name      = self::NAMES[ $id ] ?? $id;

		if ( 'embedding' === $type || in_array( '/v1/embeddings', $endpoints, true ) ) {
			if ( ! interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ) ) {
				return null;
			}
			$modalities = array( array( ModalityEnum::text() ) );
			if ( in_array( 'image', $inputs, true ) ) {
				$modalities[] = array( ModalityEnum::image() );
			}
			return new ModelMetadata(
				$id,
				$name,
				array( CapabilityEnum::embeddingGeneration() ),
				array(
					new SupportedOption( OptionEnum::inputModalities(), $modalities ),
				)
			);
		}

		if ( in_array( $type, array( 'tts', 'speech', 'text_to_speech' ), true ) || in_array( '/v1/audio/speech', $endpoints, true ) || 'empedia/tts' === $id ) {
			if ( ! interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\TextToSpeechConversion\\Contracts\\TextToSpeechConversionModelInterface' ) ) {
				return null;
			}
			return new ModelMetadata(
				$id,
				$name,
				array( CapabilityEnum::textToSpeechConversion() ),
				array(
					new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
					new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::audio() ) ) ),
					new SupportedOption( OptionEnum::outputSpeechVoice() ),
					new SupportedOption( OptionEnum::outputMimeType(), array( 'audio/mpeg' ) ),
					new SupportedOption( OptionEnum::customOptions() ),
				)
			);
		}

		// Transcription and other special models (no chat endpoint) are not offered.
		if ( '' !== $type && ! in_array( $type, array( 'chat', 'text', 'generate', 'llm' ), true ) ) {
			return null;
		}
		if ( ! empty( $endpoints ) && ! in_array( '/v1/chat/completions', $endpoints, true ) ) {
			return null;
		}
		$modalities = array( array( ModalityEnum::text() ) );
		if ( in_array( 'image', $inputs, true ) || in_array( $id, self::VISION_MODELS, true ) ) {
			$modalities[] = array( ModalityEnum::text(), ModalityEnum::image() );
		}
		return new ModelMetadata(
			$id,
			$name,
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			array(
				new SupportedOption( OptionEnum::inputModalities(), $modalities ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
			)
		);
	}

	/**
	 * Check the account-specific catalogue.
	 *
	 * @param string $modelId Model identifier.
	 * @return bool
	 */
	public function hasModelMetadata( string $modelId ): bool { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK interface uses this name.
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK interface uses this name.
				return true;
			}
		}
		return false;
	}

	/**
	 * Return metadata for an authorized model.
	 *
	 * @param string $modelId Model identifier.
	 * @return ModelMetadata
	 * @throws InvalidArgumentException If the model is unavailable.
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK interface uses this name.
		foreach ( $this->listModelMetadata() as $model ) {
			if ( $model->getId() === $modelId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- SDK interface uses this name.
				return $model;
			}
		}
		throw new InvalidArgumentException( 'Model is unavailable to this EMPEDIA key.' );
	}
}
