# Usage

Currently supported are:
 * Mistral (ai_engine.name: "MistralAI")
 * OpenAI (and all OpenAI-compatible providers, such as ollama or Open-WebUI) (ai_engine.name: "OpenAI")
 * Anthropic (experimental) (ai_engine.name: "AnthropicAI")

## How to configure Mistral

Minimal configuration:

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		),
		'ai_engine.name' => 'MistralAI',
	),
```

Example of using OpenAI: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		),
		'ai_engine.name' => 'OpenAI',
	),
```

## How to configure OpenAI compatible custom endpoint

Example of using OpenAI API against custom endpoint: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		  'url' => 'https://your.ollama-or-openwebui-werver.com',
		  'model' => 'your-model-name', // e.g. llama3:latest, see ollama.com -> models
		),
		'ai_engine.name' => 'OpenAI',
	),
```
