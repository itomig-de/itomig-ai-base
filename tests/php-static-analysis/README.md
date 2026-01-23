# How to use phpstan's static analysis for this extension

The test has to be run on a fully installed iTop instance which contains this extension.

First install phptstan:
- Open a terminal in the folder **tests/php-static-analysis** of iTop.
- Notice the composer.json file. The run the following command:

```
composer install
```

Then launch phpstan for this extension.
- Open a terminal in **the root folder of iTop** and
- Launch the command:

```
./tests/php-static-analysis/vendor/bin/phpstan analyse -c ./env-production/itomig-ai-base/tests/php-static-analysis/config/itomig-ai-base.neon
```

You should see something like:

```
10/10 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ------ ---------------------------------------------------------------------------------------------------------------------------------------- 
  Line   Engine/MistralAIEngine.php                                                                                                              
 ------ ---------------------------------------------------------------------------------------------------------------------------------------- 
  63     Instantiated class LLPhant\MistralAIConfig not found.                                                                                   
         🪪  class.notFound                                                                                                                      
         💡  Learn more at https://phpstan.org/user-guide/discovering-symbols                                                                    
         ✏️  Engine/MistralAIEngine.php                                                                                                          
  63     Parameter #1 $config of class LLPhant\Chat\MistralAIChat constructor expects LLPhant\OpenAIConfig|null, LLPhant\MistralAIConfig given.  
         🪪  argument.type                                                                                                                       
         ✏️  Engine/MistralAIEngine.php                                                                                                          
 ------ ---------------------------------------------------------------------------------------------------------------------------------------- 

 ------ -------------------------------------------------------------------------------------------------------------------------------- 
  Line   Service/AIService.php                                                                                                           
 ------ -------------------------------------------------------------------------------------------------------------------------------- 
  120    Access to an undefined property Itomig\iTop\Extension\AIBase\Service\AIService::$oAIBaseHelper.                                 
         🪪  property.notFound                                                                                                           
         💡  Learn more: https://phpstan.org/blog/solving-phpstan-access-to-undefined-property                                           
         ✏️  Service/AIService.php                                                                                                       
  175    PHPDoc tag @var has invalid value ($aAIEngines): Unexpected token "$aAIEngines", expected type at offset 9 on line 1            
         🪪  phpDoc.parseError                                                                                                           
         ✏️  Service/AIService.php                                                                                                       
  179    Variable $AIEngineClass in PHPDoc tag @var does not match any variable in the foreach loop: $aAIEngineClasses, $sAIEngineClass  
         🪪  varTag.differentVariable                                                                                                    
         ✏️  Service/AIService.php                                                                                                       
 ------ -------------------------------------------------------------------------------------------------------------------------------- 
```