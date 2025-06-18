<?php
//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'itomig-ai-base/25.2.1',
	array(
		// Identification
		//
		'label' => 'AI Base (ITOMIG GmbH)',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			'itop-structure/3.2.1',

		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'vendor/autoload.php',
		),
		'webservice' => array(

		),
		'data.struct' => array(
			// add your 'structure' definition XML files here,
		),
		'data.sample' => array(
			// add your sample data XML files here,
		),

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any

		// Default settings
		//
		'settings' => array(
			'ai_engine.configuration' => array (
				'url' => 'http://127.0.0.1:11434/api/',
				'api_key' => 'your-api-key',
				'model' => 'qwen2.5:14b',
			),
			'ai_engine.name' => 'OllamaAI',
		),
	)
);


?>
