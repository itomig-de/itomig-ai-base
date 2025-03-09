<?php
/*
 * @copyright Copyright (C) 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
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

class TriggerOnAIResponse extends Trigger
{
	public static function Init()
	{
		$aParams = array('category' => 'core/cmdb,application,grant_by_profile',
			'key_type' => 'autoincrement',
			'name_attcode' => array('description'),
			'image_attcode' => '',
			'state_attcode' => '',
			'reconc_keys' => array('description'),
			'db_table' => 'priv_trigger_airesponse',
			'db_key_field' => 'id',
			'db_finalclass_field' => '',
			'style' => new ormStyle(null, null, null, null, null, null),);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();


		MetaModel::Init_SetZListItems('details', array(
			0 => 'description',
			3 => 'action_list',
		));
		MetaModel::Init_SetZListItems('default_search', array(
			1 => 'description',
		));
		MetaModel::Init_SetZListItems('list', array(
			0 => 'finalclass',
			2 => 'description',
		));
	}
}
