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

use Itomig\iTop\Extension\AIBase\Service\AIService;

class AsyncAITask extends AsyncTask
{

	public static function Init()
	{
		$aParams = array
		(
			"category" => "core/cmdb",
			"key_type" => "autoincrement",
			"name_attcode" => "created",
			"state_attcode" => "",
			"reconc_keys" => array(),
			"db_table" => "priv_async_send_newsroom",
			"db_key_field" => "id",
			"db_finalclass_field" => "",
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		MetaModel::Init_AddAttribute(new AttributeString("prompt", array("allowed_values"=>null, "sql"=>"prompt", "default_value"=>null, "is_null_allowed"=>true, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeText("text", array("allowed_values"=>null, "sql"=>"text", "default_value"=>null, "is_null_allowed"=>true, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("callback", array("allowed_values"=>null, "sql"=>"callback", "default_value"=>null, "is_null_allowed"=>true, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeString("src_class", array("sql"=>'src_class', "is_null_allowed"=>true, "default_value"=>'', "allowed_values"=>null, "depends_on"=>array(), "always_load_in_tables"=>false)));
		MetaModel::Init_AddAttribute(new AttributeObjectKey("src_id", array("class_attcode"=>'src_class', "sql"=>'src_id', "is_null_allowed"=>true, "allowed_values"=>null, "depends_on"=>array(), "always_load_in_tables"=>false)));
		MetaModel::Init_AddAttribute(new AttributeExternalKey("userid", array("targetclass"=>"User", "jointype"=> "", "allowed_values"=>null, "sql"=>"userid", "is_null_allowed"=>true, "on_target_delete"=>DEL_MANUAL, "depends_on"=>array())));
		MetaModel::Init_AddAttribute(new AttributeExternalKey("contactid", array("targetclass"=>"Person", "jointype"=> "", "allowed_values"=>null, "sql"=>"contactid", "is_null_allowed"=>true, "on_target_delete"=>DEL_MANUAL, "depends_on"=>array())));

	}

	/**
	 * @param DBObject|null $oSrcObject
	 * @return void
	 * @throws CoreException
	 * @throws CoreUnexpectedValue
	 */
	public function SetSrcObject($oSrcObject)
	{
		if(!is_null($oSrcObject)) {
			$this->Set('src_class', get_class($oSrcObject));
			$this->Set('src_id', $oSrcObject->GetKey());
		}
	}

	/**
	 * Adds a new task to the queue for processing. This method sets the necessary attributes
	 * and inserts the object into the database.
	 *
	 * @param string $prompt The initial question or input to be queued.
	 * @param string $text Additional text or context associated with the task.
	 * @param object|null $srcObject The source object linked to this task (optional).
	 * @param string $callback The name of a callback function or method to execute after processing (optional).
	 * @param int $userkey The ID of the user submitting the task; defaults to the current user if not specified.
	 * @param int $personkey The ID of the person related to the task; defaults to the current contact if not specified.
	 *
	 * @return void
	 */
	public static function AddToQueue($prompt, $text, $srcObject = null, $callback = '', $userkey = -1, $personkey = -1): void
	{
		$oNew = new static();
		$oNew->Set('prompt', $prompt);
		$oNew->Set('text', $text);
		$oNew->Set('callback', $callback);
		$oNew->SetSrcObject($srcObject);
		if($userkey < 0) {
			$userkey = UserRights::GetUserId();
		}
		$oNew->Set('userid', $userkey);
		if($personkey < 0) {
			$personkey = UserRights::GetContactId();
		}
		$oNew->Set('contactid', $personkey);
		$oNew->DBInsert();
	}

	/**
	 * @inheritDoc
	 */
	public function DoProcess()
	{
		$oSrcObject = MetaModel::GetObject($this->Get('src_class'), $this->Get('src_id'), false);
		$prompt = $this->Get('prompt');
		$text = $this->Get('text');
		$callback = $this->Get('callback');
		$oUser = MetaModel::GetObject('User', $this->Get('userid'), false);
		$oPerson = MetaModel::GetObject('Person', $this->Get('contactid'), false);

		$oAIService = new AIService();

		$response = $oAIService->GetReply($prompt, $text, $oSrcObject);

		// Step 1: Use the callback Logic
		if(!empty($callback)) {
			if(stripos($callback, '$this->') !== false)
			{
				$sMethodName = str_ireplace('$this->', '', $callback);
				if(is_callable([$oSrcObject, $sMethodName])) {
					$oSrcObject->$sMethodName($response);
				}
			}
			// Otherwise, check if callback is callable as a static method
			elseif(is_callable($callback))
			{
				call_user_func($callback, $response, $oSrcObject);
			}
		}

		// Step 2: Execute the custom Trigger
		$oSet = new DBObjectSet(DBObjectSearch::FromOQL('SELECT TriggerOnAIResponse'));
		while ($oTrigger = $oSet->Fetch()) {
			$aContextArgs = [
				'response' => $response,
				'src->object()' => $oSrcObject,
				'contact->object()' => $oPerson,
				'user->object()' => $oUser,
			];
			$oTrigger->DoActivate($aContextArgs);
		}
		return "Executed.";
	}
}

/* SAMPLE Usage:

Person Custom Methods:

public function SendAIQuestion()
{
	$firstName = $this->Get('first_name');
	$request = "What is the origin and meaning of the name \"$firstName\"?";
	AsyncAITask::AddToQueue(
		'getCompletions',
		$request,
		$this,
		'$this->HandleAIResponse'
	);
}

public function HandleAIResponse($response)
{
	IssueLog::Info("Meaning of name " . $this->Get("first_name") . ": " . $response);
}


*/
