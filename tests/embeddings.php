<?php
/** Contract tests against a version of the WordPress PHP AI Client supporting embeddings. */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../tools/embedding-sdk/vendor/autoload.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-models.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-text-model.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-embedding-model.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-tts-model.php';
require __DIR__ . '/../plugin/includes/class-empedia-ai-provider.php';

use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\{Request,RequestOptions,Response,ApiKeyRequestAuthentication};
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

class Test_Embedding_Transport implements HttpTransporterInterface {
 public $requests = array();
 public $bad = '';
 public function send(Request $request, ?RequestOptions $options = null): Response {
  $this->requests[] = $request;
  if ($request->getHeaderAsString('Authorization') !== 'Bearer fake-key' || $request->getOptions()->getMaxRedirects() !== 0) { throw new RuntimeException('Authentication or redirect policy failed'); }
  if (substr($request->getUri(), -7) === '/models') {
   return new Response(200, array(), '{"data":[{"id":"Qwen/Qwen3-VL-Embedding-8B","model_type":"embedding","input_modalities":["text","image"]}]}');
  }
  if ($request->getUri() !== 'https://llmapi.empedia.cloud/v1/embeddings') { throw new RuntimeException('Wrong endpoint'); }
  $d = $request->getData();
  if (isset($d['input'])) {
   if ($d['input'] !== array('first text','second text') || isset($d['messages'])) { throw new RuntimeException('Text batch shape'); }
   $data = array(array('index'=>1,'embedding'=>array(2,2)),array('index'=>0,'embedding'=>array(1,1)));
  } elseif (isset($d['messages'])) {
   $item = $d['messages'][0]['content'][0] ?? array();
   if (($item['type'] ?? '') !== 'image_url' || ($item['image_url']['url'] ?? '') !== 'data:image/png;base64,aGVsbG8=' || isset($d['input'])) { throw new RuntimeException('Image message shape'); }
   $data = array(array('index'=>0,'embedding'=>array(3,3)));
  } else { throw new RuntimeException('Missing input'); }
  if ($this->bad === 'duplicate') { $data = array(array('index'=>0,'embedding'=>array(1,1)),array('index'=>0,'embedding'=>array(2,2))); }
  if ($this->bad === 'nonnumeric') { $data[0]['embedding'][0] = 'invalid'; }
  return new Response(200, array('X-Request-ID'=>'request-1'), json_encode(array('data'=>$data,'usage'=>array('prompt_tokens'=>4,'total_tokens'=>4))));
 }
}
function assert_test($condition,$message) { if (!$condition) { throw new RuntimeException($message); } }
function must_fail($callback,$message) { try { $callback(); } catch (\WordPress\AiClient\Common\Exception\InvalidArgumentException $e) { return; } catch (\WordPress\AiClient\Common\Exception\RuntimeException $e) { return; } throw new RuntimeException($message); }
$http = new Test_Embedding_Transport();
$registry = new ProviderRegistry();
$registry->setHttpTransporter($http);
$registry->registerProvider(Empedia_AI_Provider::class);
$registry->setProviderRequestAuthentication('empedia',new ApiKeyRequestAuthentication('fake-key'));
$model = $registry->getProviderModel('empedia','Qwen/Qwen3-VL-Embedding-8B');
$inputs = array(new MessagePart('first text'),new MessagePart(new File('data:image/png;base64,aGVsbG8=','image/png')),new MessagePart('second text'));
$result = $model->generateEmbeddingResult($inputs);
$vectors = array_map(function($e){return $e->getValues();},$result->getEmbeddings());
assert_test($vectors === array(array(1.0,1.0),array(3.0,3.0),array(2.0,2.0)), 'Vectors must retain input order');
assert_test($result->getDimensions() === 2 && $result->getTokenUsage()->getPromptTokens() === 8, 'Dimensions or combined usage incorrect');
assert_test(count($http->requests) === 3, 'Expected catalogue plus text and image requests');
$before = count($http->requests);
must_fail(function() use ($model) {$model->generateEmbeddingResult(array());}, 'Empty input must fail');
must_fail(function() use ($model) {$model->generateEmbeddingResult(array(new MessagePart(' ')));}, 'Empty text must fail');
must_fail(function() use ($model) {$model->generateEmbeddingResult(array(new MessagePart(new File('https://example.org/image.png','image/png'))));}, 'Remote image must fail');
assert_test(count($http->requests) === $before, 'Invalid input must fail before any request');
$cfg = new ModelConfig(); $cfg->setDimensions(128); $model->setConfig($cfg);
must_fail(function() use ($model,$inputs) {$model->generateEmbeddingResult($inputs);}, 'Unsupported dimensions must fail');
$model->setConfig(new ModelConfig());
$http->bad = 'duplicate';
must_fail(function() use ($model,$inputs) {$model->generateEmbeddingResult($inputs);}, 'Duplicate indices must fail');
$http->bad = 'nonnumeric';
must_fail(function() use ($model,$inputs) {$model->generateEmbeddingResult($inputs);}, 'Invalid vector must fail');
echo "Embedding contract tests passed\n";
