# Usage

Currently supported are:
 * Mistral (ai_engine.name: "MistralAI")
 * OpenAI (and all OpenAI-compatible providers, such as or Open-WebUI) (ai_engine.name: "OpenAI")
 * Ollama (ai_endine.name: "OllamaAI")
 * Anthropic (ai_engine.name: "AnthropicAI")
 * (WIP: Support for DeepL (translation only)

## How to configure Mistral

Minimal configuration:

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		  'url' => 'https://api.mistral.ai/v1/',
		  'model' => 'open-mistral-nemo',
		),
		'ai_engine.name' => 'MistralAI',
	),
```

Example of using OpenAI: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		  'url' => 'https://api.openai.com/v1/',
		),
		'ai_engine.name' => 'OpenAI',
	),
```

Example of using Anthropic: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		  'url' => 'https://api.anthropic.com/v1/messages',
		  'model' => 'claude-3-5-sonnet-latest',
		),
		'ai_engine.name' => 'AnthropicAI',
	),
```

Example of using Ollama: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'url' => 'https://127.0.0.1:11434/api/', // or wherever you have your ollama running
		  'model' => 'qwen2.5:14b', // see ollama.com/library for available models
		),
		'ai_engine.name' => 'OllamaAI',
	),
```

## How to configure OpenAI compatible custom endpoint

Example of using OpenAI API against custom endpoint: :

```PHP
	'itomig-ai-base' => array (
		'ai_engine.configuration' => array (
		  'api_key' => '***',
		  'url' => 'https://your.ollama-or-openwebui-server.com',
		  'model' => 'your-model-name', // e.g. llama3.1:latest, see ollama.com -> models
		),
		'ai_engine.name' => 'OpenAI',
	),
```
