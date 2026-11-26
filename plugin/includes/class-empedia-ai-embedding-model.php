<?php
/**
 * EMPEDIA embedding model.
 *
 * @package Empedia_AI_Connector
 */

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embeddings with EMPEDIA's OpenAI-compatible embeddings endpoint.
 */
class Empedia_AI_Embedding_Model extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface {
	/**
	 * Maximum number of inputs per request.
	 */
	const MAX_INPUTS = 64;

	/**
	 * Generate one embedding per input part.
	 *
	 * @param MessagePart[] $inputs Text or image parts.
	 * @return EmbeddingResult
	 * @throws InvalidArgumentException If an input is unsupported.
	 * @throws RuntimeException If the response is invalid.
	 */
	public function generateEmbeddingResult( array $inputs ): EmbeddingResult {
		if ( empty( $inputs ) || count( $inputs ) > self::MAX_INPUTS ) {
			throw new InvalidArgumentException( 'EMPEDIA embeddings need between 1 and 64 inputs.' );
		}
		if ( null !== $this->getConfig()->getDimensions() ) {
			throw new InvalidArgumentException( 'EMPEDIA does not support custom embedding dimensions.' );
		}

		$texts        = array();
		$text_indices = array();
		$images       = array();
		foreach ( array_values( $inputs ) as $index => $part ) {
			$value = $this->input_of( $part );
			if ( $part->getType()->isFile() ) {
				$images[ $index ] = $value;
			} else {
				$text_indices[] = $index;
				$texts[]        = $value;
			}
		}

		$results       = array();
		$prompt_tokens = 0;
		$total_tokens  = 0;
		$request_id    = '';
		if ( $texts ) {
			$this->append_response(
				array(
					'model' => $this->metadata()->getId(),
					'input' => $texts,
				),
				$text_indices,
				$results,
				$prompt_tokens,
				$total_tokens,
				$request_id
			);
		}
		foreach ( $images as $index => $uri ) {
			$this->append_response(
				array(
					'model'    => $this->metadata()->getId(),
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => array(
								array(
									'type'      => 'image_url',
									'image_url' => array( 'url' => $uri ),
								),
							),
						),
					),
				),
				array( $index ),
				$results,
				$prompt_tokens,
				$total_tokens,
				$request_id
			);
		}
		ksort( $results );
		$size       = count( reset( $results ) );
		$embeddings = array();
		foreach ( $results as $values ) {
			if ( count( $values ) !== $size ) {
				throw new RuntimeException( 'EMPEDIA returned embeddings of different sizes.' );
			}
			$embeddings[] = new Embedding( $values, $size );
		}
		return new EmbeddingResult(
			$request_id ? $request_id : uniqid( 'empedia-emb-', true ),
			$embeddings,
			$size,
			new TokenUsage( $prompt_tokens, 0, $total_tokens ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Send a batch and assign each returned vector to its input position.
	 *
	 * @param array  $body Request body.
	 * @param array  $positions Original input positions.
	 * @param array  $results Result vectors by position.
	 * @param int    $prompt_tokens Accumulated input tokens.
	 * @param int    $total_tokens Accumulated total tokens.
	 * @param string $request_id Last request ID.
	 * @return void
	 * @throws RuntimeException For malformed API responses.
	 */
	private function append_response( array $body, array $positions, array &$results, int &$prompt_tokens, int &$total_tokens, string &$request_id ): void {
		$options = $this->getRequestOptions();
		$options = $options ? clone $options : new RequestOptions();
		$options->setMaxRedirects( 0 );
		$request  = new Request( HttpMethodEnum::POST(), Empedia_AI_Provider::url( 'embeddings' ), array( 'Content-Type' => 'application/json' ), $body, $options );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		$data = $response->getData();
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) || count( $data['data'] ) !== count( $positions ) ) {
			throw new RuntimeException( 'EMPEDIA returned an invalid embedding response.' );
		}
		$seen = array();
		foreach ( $data['data'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['index'], $row['embedding'] ) || ! is_int( $row['index'] ) || ! isset( $positions[ $row['index'] ] ) || isset( $seen[ $row['index'] ] ) || ! is_array( $row['embedding'] ) || empty( $row['embedding'] ) ) {
				throw new RuntimeException( 'EMPEDIA returned an invalid embedding.' );
			}
			$values = array();
			foreach ( $row['embedding'] as $value ) {
				if ( ( ! is_int( $value ) && ! is_float( $value ) ) || ! is_finite( (float) $value ) ) {
					throw new RuntimeException( 'EMPEDIA returned an invalid embedding value.' );
				}
				$values[] = (float) $value;
			}
			$seen[ $row['index'] ]                  = true;
			$results[ $positions[ $row['index'] ] ] = $values;
		}
		$usage          = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
		$prompt         = (int) ( $usage['prompt_tokens'] ?? 0 );
		$prompt_tokens += $prompt;
		$total_tokens  += (int) ( $usage['total_tokens'] ?? $prompt );
		$request_id     = (string) $response->getHeaderAsString( 'X-Request-ID' );
	}

	/**
	 * Convert a message part to an API input.
	 *
	 * @param mixed $part Message part.
	 * @return string
	 * @throws InvalidArgumentException If the part is unsupported.
	 */
	private function input_of( $part ): string {
		if ( ! $part instanceof MessagePart ) {
			throw new InvalidArgumentException( 'EMPEDIA embeddings expect message parts.' );
		}
		if ( $part->getType()->isText() ) {
			$text = (string) $part->getText();
			if ( '' === trim( $text ) ) {
				throw new InvalidArgumentException( 'EMPEDIA embeddings do not accept empty text.' );
			}
			return $text;
		}
		if ( $part->getType()->isFile() ) {
			$file = $part->getFile();
			if ( $file && $file->isImage() && in_array( $file->getMimeType(), array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ), true ) ) {
				$uri = $file->isInline() ? $file->getDataUri() : '';
				if ( $uri ) {
					return $uri;
				}
			}
		}
		throw new InvalidArgumentException( 'EMPEDIA embeddings accept only text and inline images.' );
	}
}
