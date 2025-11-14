<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 * @author DavidM. Gümbel <david.guembel@itomig.de>
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with iTop. If not, see <http://www.gnu.org/licenses/>
 */

Dict::Add('FR FR', 'French', 'Français', array(
    'Menu:AIBaseDiagnostics' => 'Diagnostic IA',
    'itomig-ai-base/Operation:Show/Title' => 'Diagnostic IA',
    'itomig-ai-base/Operation:show/Title' => 'Diagnostic IA',

    // Page and sections
    'Diagnostics:PageTitle' => 'Diagnostic IA',
    'Diagnostics:ConfigPanel:Title' => 'État de la configuration',
    'Diagnostics:ConfigPanel:EngineName' => 'Nom du moteur IA',
    'Diagnostics:ConfigPanel:EngineConfig' => 'Configuration du moteur',
    'Diagnostics:ConfigPanel:NotFound' => 'Non trouvé dans la configuration',

    // Test panel
    'Diagnostics:TestPanel:Title' => 'Tester la connexion IA',
    'Diagnostics:TestPanel:MessageLabel' => 'Message de test',
    'Diagnostics:TestPanel:MessagePlaceholder' => 'Entrez votre message de test ici...',
    'Diagnostics:TestPanel:VerboseLabel' => 'Afficher les informations de diagnostic détaillées',
    'Diagnostics:TestPanel:SubmitButton' => 'Envoyer au moteur IA',
    'Diagnostics:TestPanel:SystemPromptLabel' => 'Prompt système',
    'Diagnostics:TestPanel:SystemPromptSelect' => 'Sélectionner un prompt système (optionnel)',
    'Diagnostics:TestPanel:SystemPromptNone' => '(Aucun - utiliser le défaut)',

    // Results
    'Diagnostics:ResultPanel:Title' => 'Réponse IA',
    'Diagnostics:ErrorPanel:Title' => 'Erreur',

    // Verbose diagnostics
    'Diagnostics:VerbosePanel:Title' => 'Détails du diagnostic',
    'Diagnostics:VerbosePanel:RequestInfo' => 'Informations de la requête',
    'Diagnostics:VerbosePanel:Message' => 'Message',
    'Diagnostics:VerbosePanel:SystemInstruction' => 'Instruction système',
    'Diagnostics:VerbosePanel:MessageLength' => 'Longueur du message',
    'Diagnostics:VerbosePanel:InstructionLength' => 'Longueur de l\'instruction',
    'Diagnostics:VerbosePanel:Timing' => 'Informations temporelles',
    'Diagnostics:VerbosePanel:Duration' => 'Durée totale',
    'Diagnostics:VerbosePanel:StartTime' => 'Heure de début',
    'Diagnostics:VerbosePanel:EndTime' => 'Heure de fin',
    'Diagnostics:VerbosePanel:EngineConfig' => 'Configuration du moteur',

    // Prompts panel
    'Diagnostics:PromptsPanel:Title' => 'Prompts système actuels',
    'Diagnostics:PromptsPanel:BuiltIn' => 'Intégré',
    'Diagnostics:PromptsPanel:Custom' => 'Personnalisé',
    'Diagnostics:PromptsPanel:Overridden' => 'Remplacé',
    'Diagnostics:PromptsPanel:NoCustom' => 'Aucun prompt personnalisé configuré',

    // Editor panel
    'Diagnostics:EditorPanel:Title' => 'Générer le code de configuration',
    'Diagnostics:EditorPanel:SelectPrompt' => 'Sélectionner un prompt à modifier',
    'Diagnostics:EditorPanel:SelectPlaceholder' => '-- Choisir un prompt --',
    'Diagnostics:EditorPanel:NewPrompt' => 'Ou créer un nouveau prompt',
    'Diagnostics:EditorPanel:PromptKeyLabel' => 'Clé du prompt',
    'Diagnostics:EditorPanel:PromptKeyPlaceholder' => 'ex. monPromptPersonnalise',
    'Diagnostics:EditorPanel:PromptValueLabel' => 'Texte du prompt',
    'Diagnostics:EditorPanel:PromptValuePlaceholder' => 'Entrez les instructions du prompt système...',
    'Diagnostics:EditorPanel:GenerateButton' => 'Générer le code de configuration',
    'Diagnostics:EditorPanel:Warning' => 'Note : Cet outil génère du code PHP que vous devez ajouter manuellement à votre fichier de configuration. Les fichiers de configuration sont en lecture seule en production pour des raisons de sécurité.',

    // Save results
    'Diagnostics:SaveError' => 'Erreur de validation',
    'Diagnostics:SaveFallback:Title' => 'Code de configuration généré',
    'Diagnostics:SaveFallback:Message' => 'Ajoutez ce code à votre fichier de configuration dans conf/production/config-itop.php :',
));
