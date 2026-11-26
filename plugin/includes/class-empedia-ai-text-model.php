<?php
/**
 * EMPEDIA text generation model.
 *
 * @package Empedia_AI_Connector
 */

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text generation with EMPEDIA's OpenAI-compatible chat endpoint.
 */
class Empedia_AI_Text_Model extends AbstractOpenAiCompatibleTextGenerationModel {
	/**
	 * Build an EMPEDIA request.
	 *
	 * @param HttpMethodEnum $method HTTP method.
	 * @param string         $path API path.
	 * @param array          $headers Request headers.
	 * @param mixed          $data Request payload.
	 * @return Request
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$options = $this->getRequestOptions();
		$options = $options ? clone $options : new RequestOptions();
		$options->setMaxRedirects( 0 );
		return new Request( $method, Empedia_AI_Provider::url( $path ), $headers, $data, $options );
	}
}
