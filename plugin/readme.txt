=== EMPEDIA AI Connector ===
Contributors: urbanstudiogmbh
Tags: ai, connector, empedia
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect the WordPress AI Client to EMPEDIA for text, image input, embeddings and text to speech.

== Description ==

EMPEDIA AI Connector makes the EMPEDIA models available to plugins that use the WordPress AI Client. Each model is offered for its purpose: text generation with image input, embeddings (vector representations of text) and text to speech. Only models that your API key may use are listed. Available capabilities depend on the installed WordPress AI Client version. Other plugins must use the WordPress AI Client to access the connector. This plugin does not add a public chatbot.

An EMPEDIA account and API key with access to the model are required. API usage is billed by EMPEDIA according to the customer's account and applicable pricing. An initial minimum account top-up may apply. Review the current pricing and terms before enabling the service.

== Installation ==

1. Install and activate the plugin.
2. Open Settings > Connectors, choose EMPEDIA, and enter an EMPEDIA API key with model access.
3. Use a WordPress AI Client compatible plugin that offers text generation, embeddings or speech output.

== External services ==

The plugin connects to EMPEDIA at https://llmapi.empedia.cloud/v1. The WordPress AI Client fetches the list of models available to the configured API key and sends data to EMPEDIA only when a compatible plugin requests it: prompts and any selected images for text generation (chat/completions), texts and any selected images (or their URLs) for embeddings (embeddings) and texts for speech output (audio/speech). For speech output the list of available voices may be fetched (audio/voices). These requests use the configured API key. No content is sent by this connector merely on installation.

EMPEDIA Cloud: https://empedia.cloud/
Privacy policy: https://empedia.cloud/datenschutz.html
Terms: https://empedia.cloud/agb.html

Please consult the terms and privacy information in your EMPEDIA Cloud account before use. The site owner is responsible for evaluating which content is sent to the service.

== Frequently Asked Questions ==

= Does this plugin add a chatbot? =
No. It provides a model connection for other plugins using the WordPress AI Client.

= Which models and capabilities are supported? =
Text generation with text and image input (for example Gemma 4), embeddings (for example Qwen3 Embedding) and text to speech, as far as these models are available to your API key. Speech recognition (transcription) and document conversion (for example MinerU) are not supported because the WordPress AI Client does not currently provide these capabilities.

== Changelog ==

= 0.1.0 =
* Initial connector: text generation with image input, embeddings and text to speech.
