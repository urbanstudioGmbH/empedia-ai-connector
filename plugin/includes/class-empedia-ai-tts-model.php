<?php
/**
 * EMPEDIA text-to-speech model.
 *
 * @package Empedia_AI_Connector
 */

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speech output with EMPEDIA's audio/speech endpoint (MP3).
 */
class Empedia_AI_Tts_Model extends AbstractApiBasedModel implements TextToSpeechConversionModelInterface {
	/**
	 * Maximum characters per request.
	 */
	const MAX_CHARS = 4096;

	/**
	 * Convert the prompt text to speech.
	 *
	 * @param Message[] $prompt Prompt messages.
	 * @return GenerativeAiResult
	 * @throws InvalidArgumentException If the text is empty or too long.
	 * @throws RuntimeException If the response is invalid.
	 */
	public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$texts = array();
		foreach ( $prompt as $message ) {
			if ( ! $message instanceof Message ) {
				continue;
			}
			foreach ( $message->getParts() as $part ) {
				if ( $part->getType()->isText() ) {
					$texts[] = (string) $part->getText();
				}
			}
		}
		$text   = trim( implode( "\n", $texts ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( 0 === $length || $length > self::MAX_CHARS ) {
			throw new InvalidArgumentException( 'EMPEDIA speech needs between 1 and 4096 characters of text.' );
		}

		$body     = array(
			'model'           => $this->metadata()->getId(),
			'voice'           => $this->voice(),
			'input'           => $text,
			'response_format' => 'mp3',
			'stream_format'   => 'audio',
		);
		$request  = $this->request( HttpMethodEnum::POST(), 'audio/speech', $body );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		$audio = (string) $response->getBody();
		if ( '' === $audio ) {
			throw new RuntimeException( 'EMPEDIA returned empty audio.' );
		}

		$file    = new File( 'data:audio/mpeg;base64,' . base64_encode( $audio ), 'audio/mpeg' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary audio is wrapped as a data URI for the SDK.
		$message = new ModelMessage( array( new MessagePart( $file ) ) );
		$id      = $response->getHeaderAsString( 'X-Request-ID' );

		return new GenerativeAiResult(
			$id ? $id : uniqid( 'empedia-tts-', true ),
			array( new Candidate( $message, FinishReasonEnum::stop() ) ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Choose the configured voice or the first voice offered for the model.
	 *
	 * @return string
	 * @throws RuntimeException If no voice is available.
	 */
	private function voice(): string {
		$config = $this->getConfig();
		$voice  = $config->getOutputSpeechVoice();
		if ( is_string( $voice ) && '' !== $voice ) {
			return $voice;
		}
		$custom = $config->getCustomOptions();
		if ( isset( $custom['voice'] ) && is_string( $custom['voice'] ) && '' !== $custom['voice'] ) {
			return $custom['voice'];
		}
		$request  = $this->request( HttpMethodEnum::GET(), 'audio/voices?model=' . rawurlencode( $this->metadata()->getId() ), null );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		$data = $response->getData();
		if ( is_array( $data ) && isset( $data['data'][0]['id'] ) && is_string( $data['data'][0]['id'] ) ) {
			return $data['data'][0]['id'];
		}
		throw new RuntimeException( 'EMPEDIA offers no voice for this model.' );
	}

	/**
	 * Build an authenticated EMPEDIA request without redirects.
	 *
	 * @param HttpMethodEnum $method HTTP method.
	 * @param string         $path API path.
	 * @param array|null     $body JSON payload.
	 * @return Request
	 */
	private function request( HttpMethodEnum $method, string $path, $body ): Request {
		$options = $this->getRequestOptions();
		$options = $options ? clone $options : new RequestOptions();
		$options->setMaxRedirects( 0 );
		$headers = null === $body ? array() : array( 'Content-Type' => 'application/json' );
		$request = new Request( $method, Empedia_AI_Provider::url( $path ), $headers, $body, $options );
		return $this->getRequestAuthentication()->authenticateRequest( $request );
	}
}
