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
		$payload = array();
		foreach ( $inputs as $part ) {
			$payload[] = $this->input_of( $part );
		}
		$body       = array(
			'model' => $this->metadata()->getId(),
			'input' => $payload,
		);
		$dimensions = $this->getConfig()->getDimensions();
		if ( null !== $dimensions ) {
			$body['dimensions'] = (int) $dimensions;
		}

		$options = $this->getRequestOptions();
		$options = $options ? clone $options : new RequestOptions();
		$options->setMaxRedirects( 0 );
		$request  = new Request( HttpMethodEnum::POST(), Empedia_AI_Provider::url( 'embeddings' ), array( 'Content-Type' => 'application/json' ), $body, $options );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		$data = $response->getData();
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			throw new RuntimeException( 'EMPEDIA returned an invalid embedding response.' );
		}
		$rows = $data['data'];
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) ( $a['index'] ?? 0 ) <=> (int) ( $b['index'] ?? 0 );
			}
		);
		if ( count( $rows ) !== count( $payload ) ) {
			throw new RuntimeException( 'EMPEDIA returned an unexpected number of embeddings.' );
		}
		$embeddings = array();
		$size       = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['embedding'] ) || ! is_array( $row['embedding'] ) || empty( $row['embedding'] ) ) {
				throw new RuntimeException( 'EMPEDIA returned an invalid embedding.' );
			}
			$values = array_map( 'floatval', array_values( $row['embedding'] ) );
			if ( 0 === $size ) {
				$size = count( $values );
			} elseif ( count( $values ) !== $size ) {
				throw new RuntimeException( 'EMPEDIA returned embeddings of different sizes.' );
			}
			$embeddings[] = new Embedding( $values, $size );
		}

		$usage  = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
		$prompt = (int) ( $usage['prompt_tokens'] ?? 0 );
		$total  = (int) ( $usage['total_tokens'] ?? $prompt );
		$id     = $response->getHeaderAsString( 'X-Request-ID' );

		return new EmbeddingResult(
			$id ? $id : uniqid( 'empedia-emb-', true ),
			$embeddings,
			$size,
			new TokenUsage( $prompt, 0, $total ),
			$this->providerMetadata(),
			$this->metadata()
		);
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
			if ( $file && $file->isImage() ) {
				$uri = $file->isInline() ? $file->getDataUri() : $file->getUrl();
				if ( $uri ) {
					return $uri;
				}
			}
		}
		throw new InvalidArgumentException( 'EMPEDIA embeddings accept only text and images.' );
	}
}
