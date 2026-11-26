<?php
/** SDK contract test; set WP_AI_CLIENT_AUTOLOAD to WordPress's SDK autoloader. */
$autoload = getenv( 'WP_AI_CLIENT_AUTOLOAD' );
if ( ! $autoload || ! is_file( $autoload ) ) { fwrite( STDERR, "Set WP_AI_CLIENT_AUTOLOAD to wp-includes/php-ai-client/autoload.php\n" ); exit( 2 ); }
define( 'ABSPATH', __DIR__ . '/' );
require $autoload;
require __DIR__ . '/../plugin/includes/class-empedia-ai-models.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-text-model.php';
if ( interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ) ) { require __DIR__ . '/../plugin/includes/class-empedia-ai-embedding-model.php'; }
if ( interface_exists( '\\WordPress\\AiClient\\Providers\\Models\\TextToSpeechConversion\\Contracts\\TextToSpeechConversionModelInterface' ) ) { require __DIR__ . '/../plugin/includes/class-empedia-ai-tts-model.php'; }
require __DIR__ . '/../plugin/includes/class-empedia-ai-provider.php';

use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Files\DTO\File;

class Empedia_Test_Transport implements HttpTransporterInterface {
	public $requests = array();
	public $catalog = '{"data":[{"id":"google/gemma-4-26B-A4B-it"},{"id":"audio-model","model_type":"transcription"},{"id":"empedia/tts","model_type":"tts"}]}';
	public $status = 200;
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		$this->requests[] = $request;
		check( $request->getOptions() && 0 === $request->getOptions()->getMaxRedirects(), 'Redirects must be disabled' );
		if ( substr( $request->getUri(), -7 ) === '/models' ) {
			return new Response( $this->status, array(), $this->catalog );
		}
		if ( substr( $request->getUri(), -13 ) === '/audio/speech' ) { return new Response( 200, array(), 'test-audio' ); }
		return new Response( 200, array(), '{"id":"test","choices":[{"index":0,"message":{"role":"assistant","content":"Hello"},"finish_reason":"stop"}],"usage":{"prompt_tokens":1,"completion_tokens":1,"total_tokens":2}}' );
	}
}
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$transport = new Empedia_Test_Transport();
$registry = new ProviderRegistry();
$registry->setHttpTransporter( $transport );
$registry->registerProvider( Empedia_AI_Provider::class );
check( ! $registry->isProviderConfigured( 'empedia' ), 'No key must not configure provider' );
$registry->setProviderRequestAuthentication( 'empedia', new ApiKeyRequestAuthentication( 'test-key' ) );
check( $registry->isProviderConfigured( 'empedia' ), 'Provider with key should be configured' );
$models = Empedia_AI_Provider::modelMetadataDirectory()->listModelMetadata();
check( count( $models ) === 2, 'Text and speech models should be discovered; transcription is unsupported' );
$registry->getProviderModel( 'empedia', 'google/gemma-4-26B-A4B-it' )->generateTextResult( array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Describe this.' ), new MessagePart( new File( 'data:image/png;base64,aGVsbG8=', 'image/png' ) ) ) ) ) );
$request = end( $transport->requests );
check( $request->getUri() === 'https://llmapi.empedia.cloud/v1/chat/completions', 'Wrong endpoint' );
check( $request->getHeaderAsString( 'Authorization' ) === 'Bearer test-key', 'Missing key' );
$data = $request->getData();
check( $data['model'] === 'google/gemma-4-26B-A4B-it', 'Wrong model' );
check( strpos( json_encode( $data, JSON_UNESCAPED_SLASHES ), 'data:image/png;base64,aGVsbG8=' ) !== false, 'Image not passed to model' );
$config = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
$config->setOutputSpeechVoice( 'test-voice' );
$speech = $registry->getProviderModel( 'empedia', 'empedia/tts', $config );
$speech->convertTextToSpeechResult( array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Hello' ) ) ) ) );
$request = end( $transport->requests );
check( $request->getUri() === 'https://llmapi.empedia.cloud/v1/audio/speech', 'Wrong speech endpoint' );
check( $request->getData()['voice'] === 'test-voice', 'Wrong speech voice' );
$fresh = new Empedia_AI_Models();
$fresh->setHttpTransporter( $transport );
$fresh->setRequestAuthentication( new ApiKeyRequestAuthentication( 'test-key' ) );
$transport->catalog = '{"data":[]}';
check( ! $fresh->hasModelMetadata( 'google/gemma-4-26B-A4B-it' ), 'Unlisted model must not be offered' );
$transport->catalog = 'invalid';
$fresh = new Empedia_AI_Models();
$fresh->setHttpTransporter( $transport );
$fresh->setRequestAuthentication( new ApiKeyRequestAuthentication( 'test-key' ) );
$rejected = false;
try { $fresh->listModelMetadata(); } catch ( \WordPress\AiClient\Common\Exception\RuntimeException $e ) { $rejected = true; }
check( $rejected, 'Invalid response must fail' );
echo "SDK contract tests passed\n";
