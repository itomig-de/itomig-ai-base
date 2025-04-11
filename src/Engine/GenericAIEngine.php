<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 * @author David Gümbel <david.guembel@itomig.de>
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

namespace Itomig\iTop\Extension\AIBase\Engine;

use Dict;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;

abstract class GenericAIEngine implements iAIEngineInterface
{
	/**
	 * @var string $url
	 */
	protected $url;

	/**
	 * @var string $apiKey
	 */
	protected $apiKey;

	/**
	 * @var string $model
	 */
	protected $model;

	public function __construct($url, $apiKey, $model)
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
	}

}

