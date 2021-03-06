<?php

/**
 * Internal Model
 * 
 * @package ApplicationModel
 * @subpackage InternalModel
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Internal Object
 * 
 * @package ApplicationModel
 * @subpackage InternalModel
 */
class Application_Model_Internal {

	/**
	 * Required fields for diffrent MSG_TYPES
	 * 
	 * @var array $require_fields
	 */
	protected static $require_fields = array(
		'Check' => array("MSG_TYPE", "TO", "NUMBER"),
		'Request' => array("MSG_TYPE", "TO", "NUMBER", "PORT_TIME"),
		'Update' => array("MSG_TYPE", "TO", "NUMBER", "PORT_TIME"),
		'Cancel' => array("MSG_TYPE", "TO", "NUMBER"),
		'Execute' => array("MSG_TYPE", "TO", "NUMBER"),
		'Return' => array("MSG_TYPE", "TO", "NUMBER"),
		'Publish' => array("MSG_TYPE", "TO", "NUMBER"),
		'Cancel_publish' => array("MSG_TYPE", "TO", "NUMBER"),
		'KD_update' => array("MSG_TYPE", "TO", "NUMBER"),
		'Up_system' => array("MSG_TYPE", "TO"),
		'Down_system' => array("MSG_TYPE", "TO"),
		'Inquire_number' => array("MSG_TYPE", "FROM", "NUMBER"),
	);

	/**
	 *
	 * @var array
	 */
	protected $params;

	/**
	 *
	 * @var string
	 */
	protected $error_msg = null;

	/**
	 * Constructor
	 * Receives Params and uses setParams to put them in params property
	 * 
	 * @param array $params 
	 */
	public function __construct($params) {
		$this->setParams($params);
	}

	/**
	 *
	 * @param array $data 
	 */
	protected function setParams($data) {
		$this->params = $data;
	}

	/**
	 *
	 * @return array the message params 
	 */
	public function getParams() {
		return $this->params;
	}

	protected function setReqID($reqID) {
		$this->reqID = $reqID;
	}

	/**
	 *
	 * @return array the message params 
	 */
	public function getReqID() {
		return $this->reqID;
	}

	/**
	 * method to create request row in the table including unique request id
	 * save to request table request_id has to be consisten to id of the table
	 * 
	 * @return mixed request_id string or FALSE
	 */
	protected function createRequestId() {

		$tbl = new Application_Model_DbTable_Requests(Np_Db::master());
		$adapter = $tbl->getAdapter();
		$adapter->beginTransaction();
		try {
			$_id = $tbl->insert(array());
			$id = substr("0000" . $_id, -5, 5);

			$request_id = "NP";
			$request_id .= $this->params['FROM'];  //AA
			$request_id .= $this->params['TO'];  //BB
			$request_id .= date("ymd");  //YYMMDD
			$request_id .= $id;
			$request_id .= "0001"; //ZZZZ";      //for now nothing

			if (
				(strtoupper($this->params['MSG_TYPE']) != 'UP_SYSTEM' && strtoupper($this->params['MSG_TYPE']) != 'DOWN_SYSTEM')) {
				$row_insert = array(
					'request_id' => $request_id,
					'status' => 1,
					'last_transaction' => $this->params["MSG_TYPE"],
					'number' => $this->params["NUMBER"],
				);
				if (isset($this->params['MSG_TYPE']) == 'CHECK' && isset($this->params['IDENTIFICATION_VALUE'])) {
					$row_insert['flags'] = json_encode(array('identification_value' => $this->params['IDENTIFICATION_VALUE']));
				}
			} else {
				$row_insert = array(
					'request_id' => $request_id,
					'status' => 1,
					'last_transaction' => $this->params["MSG_TYPE"]
				);
			}
			// we set to from & to as the direction of number transfer
			if (strtoupper($this->params['MSG_TYPE']) != 'RETURN_NUMBER') {
				$row_insert['from_provider'] = $this->params["TO"];
				$row_insert['to_provider'] = $this->params["FROM"];
			} else {
				$row_insert['from_provider'] = $this->params["FROM"];
				$row_insert['to_provider'] = $this->params["TO"];
			}
			if (isset($this->params["AUTO_CHECK"]) && $this->params["AUTO_CHECK"]) {
				$row_insert['auto_check'] = 1;
//				$row_insert['transfer_time'] = Application_Model_General::getTimeInSqlFormat($this->params['PORT_TIME']);
			}

			$tbl->update($row_insert, "id = " . $_id);
			$adapter->commit();
			return $request_id;
		} catch (Exception $e) {
			error_log("Cannot create request ID");
			$adapter->rollBack();
		}
		return FALSE;
	}

	protected function getRequestId() {
		if (isset($this->params['REQID'])) {
			$this->setReqID($this->params['REQID']);
			return $this->params['REQID'];
		}

		if (isset($this->params['MSG_TYPE']) && $this->params['MSG_TYPE'] == "Request") {
			if ($this->previousCheckExists()) {
				$this->params['AUTO_CHECK'] = 0;
			} else {
				$this->params['MSG_TYPE'] = "Check";
				$this->params['AUTO_CHECK'] = 1;
			}
		}
		if (isset($this->params['MSG_TYPE']) && $this->params['MSG_TYPE'] == "Check" && Application_Model_General::previousCheck($this->params['NUMBER'])
			|| $this->params['MSG_TYPE'] == "Inquire_number" //might need to take these off because of standard process flow
			|| $this->params['MSG_TYPE'] == "Return"
			|| $this->params['MSG_TYPE'] == "Execute"
			|| $this->params['MSG_TYPE'] == "Publish"
			|| $this->params['MSG_TYPE'] == "Cancel_publish"
			|| $this->params['MSG_TYPE'] == "Up_system"
			|| $this->params['MSG_TYPE'] == "Down_system") {
			$this->params['REQUEST_ID'] = $this->createRequestId();
		} elseif ($this->params['MSG_TYPE'] == "Check" && !Application_Model_General::previousCheck($this->params['NUMBER'])) {
			$this->setErrorMsg("the number you have submitted is already in process");
			return FALSE;
		} elseif (($this->params['MSG_TYPE'] == "Cancel" &&
			Application_Model_General::previousCheck($this->params['NUMBER'])) ||
			($this->params['MSG_TYPE'] == "Update" &&
			Application_Model_General::previousCheck($this->params['NUMBER']))) {
			$this->setErrorMsg("invalid order of transactions .");
			return FALSE;
		}


		return TRUE;
	}

	/**
	 * if prevalidate and request id return true.
	 * sends params to internal/prodivder (action for sending to provider)
	 * via GET in new process
	 *
	 * @return string "wait" or bool FALSE
	 */
	public function Execute() {
		Application_Model_General::writeToLog($this->params);
		if ($this->PreValidate() && $this->getRequestId()) {
			if (Application_Model_General::forkProcess("/internal/provider", $this->params)) {
				return "wait";
			}
		}
		return false;
	}

	/**
	 * automatic response for  CheckResponse,RequestResponse and UpdateResponse
	 * sent to internal/provider via GET in new PROCESS
	 * @param string $type 
	 */
	public function sendAutoRequest($type) {
		$request = null;
		switch ($type) {
			case "CheckResponse":
				if (($transfer_time = Application_Model_General::isAutoCheck($this->params['REQUEST_ID'])) !== FALSE) {
					$request = array(
						"REQUEST_ID" => $this->params['REQUEST_ID'],
						"MSG_TYPE" => "Request",
						"TO" => $this->params['FROM'],
						"PORT_TIME" => $transfer_time,
						"NUMBER" => $this->params['NUMBER'],
						"PROCESS_TYPE" => $this->params['PROCESS_TYPE'],
						"FROM" => $this->params['TO'],
						"RETRY_DATE" => Application_Model_General::getDateIso(),
						"RETRY_NO" => $this->params['RETRY_NO'],
						"VERSION_NO" => Application_Model_General::getSettings("VersionNo"),
					);
				}
				break;
			case "RequestResponse":
			case "UpdateResponse":
			case "CancelResponse":
				$request = array(
					"REQUEST_ID" => $this->params['REQUEST_ID'],
					"MSG_TYPE" => "KD_update",
					"FROM" => Application_Model_General::getSettings("InternalProvider"),
					"TO" => "KD",
					"KD_update_type" => strtoupper(str_ireplace("Response", "", $type)),
					"PROCESS_TYPE" => $this->params['PROCESS_TYPE'],
					"NUMBER" => $this->params['NUMBER'],
					"RETRY_DATE" => Application_Model_General::getDateIso(),
					"RETRY_NO" => $this->params['RETRY_NO'],
					"VERSION_NO" => Application_Model_General::getSettings("VersionNo"),
				);
				break;
		}
		if ($request) {
			Application_Model_General::forkProcess("/internal/provider", $request);
		}
	}

	/**
	 * If Request is sent before T2DR2 timer return FALSE 
	 * 
	 * @return BOOL 
	 */
	protected function previousCheckExists() {
		$tbl = new Application_Model_DbTable_Requests(Np_Db::slave());
		$select = $tbl->select()->order("id DESC")
			->where('number=?', $this->params['NUMBER'])
			->order('id DESC');
		$result = $select->query()->fetchObject();
		if ($result) {
			$last_request_time_diff = time() - strtotime($result->last_requests_time);
			if ((in_array($result->last_transaction, Application_Model_General::$inProcess)) ||
				($result->last_transaction == "Check_response" &&
				$last_request_time_diff < Np_Timers::get("T2DR2")
				)) {
				return true;
			}
		}
		return false;
	}

	/**
	 *
	 * @return String transfer date of autocheck if it's autocheck else false
	 */
	protected function isAutoCheck() {

		$tbl = new Application_Model_DbTable_Requests(Np_Db::slave());
		$select = $tbl->select()
			->where('request_id=?', $this->params['REQUEST_ID'])
			->order('id DESC');
		$result = $select->query()->fetchObject();
		if ($result && $result->auto_check) {
			$datetime = new Zend_Date($result->transfer_time, 'yyyy-MM-dd HH:mm:ss', new Zend_Locale('he_IL'));
			return $datetime->getTimestamp();
		}
		return false;
	}

	/**
	 *
	 * @return bool
	 */
	protected function PreValidate() {
		if (isset($this->params['MSG_TYPE'])) {
			if ($this->params['MSG_TYPE'] != "Up_system" && $this->params['MSG_TYPE'] != "Down_system") {
				if (!$this->params['NUMBER'] || !is_numeric($this->params['NUMBER'])) {

					$this->setErrorMsg("the number you have submitted is invalid");
					return FALSE;
				}
			}
			$validator = self::$require_fields[$this->params['MSG_TYPE']];
			foreach ($validator as $key) {
				if (!isset($this->params[$key])) {
					$this->setErrorMsg("Missing field: " . $key);
					return false;
				}
			}
			return true;
		} else {

			return false;
		}
	}

	/**
	 *
	 * @return string 
	 */
	public function getErrorMsg() {
		return $this->error_msg;
	}

	/**
	 * sets error msg in error_msg property
	 * 
	 * @return void 
	 */
	public function setErrorMsg($string) {
		$this->error_msg = $string;
	}

	/**
	 * 
	 */
	public function SendErrorToInternal() {
		
	}

	/**
	 * send http request to internal RPC and return response string.
	 * 
	 * @return string 
	 */
	public function SendRequestToInternal($ack = "Ack00", $rejectReasonCode = "OK", $IDVALUE = NULL) {
		if ($this->params['FROM'] != Application_Model_General::getSettings('InternalProvider')) {
			$provider = $this->params['FROM'];
		} else {
			$provider = $this->params['TO'];
		}
		if (!$rejectReasonCode || $rejectReasonCode === "" || $rejectReasonCode == " ") {
			$rejectReasonCode = "OK";
		}
		if ($IDVALUE == FALSE) {
			$IDVALUE = "no details";
		}
		$dataToPost = array(
			'number' => $this->params['NUMBER'], //check is set otherwise select number from DB from request_id
			'identification_value' => $IDVALUE,
			'provider' => $provider,
			'msgType' => $this->params['MSG_TYPE'],
			'reqId' => $this->params['REQUEST_ID'],
			'msgDesc' => "Done",
			'ack' => $ack,
			'resultCode' => $rejectReasonCode,
			'more' => '',
		);
		if (isset($this->params['NUMBER_TYPE'])) {
			$dataToPost['number_type'] = $this->params['NUMBER_TYPE'];
			if ($dataToPost['number_type'] == "R") {
				$dataToPost['to_number'] = $this->params['TO_NUMBER'];
				$dataToPost['from_number'] = $this->params['FROM_NUMBER'];
				unset($dataToPost['number']);
			} elseif ($dataToPost['number_type'] == "S") {
				$dataToPost['number'] = $this->params['NUMBER'];
				$dataToPost['number_type'] = $this->params['NUMBER_TYPE'];
			}
		}
		if (Application_Model_General::isMsgResponse($this->params['MSG_TYPE'])) {

			$dataToPost['approval_ind'] = $this->params['APPROVAL_IND'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "KD_UPDATE_RESPONSE") {
			$dataToPost['KD_update_type'] = $this->params['KD_UPDATE_TYPE'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "EXECUTE_RESPONSE") {
			$dataToPost['disconnect_time'] = $this->params['DISCONNECT_TIME'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "REQUEST") {
			$dataToPost['port_time'] = $this->params['PORT_TIME'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "UPDATE") {
			$dataToPost['port_time'] = $this->params['PORT_TIME'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "PUBLISH") {
			$dataToPost['donor'] = $this->params['DONOR'];
			$dataToPost['publish_type'] = $this->params['PUBLISH_TYPE'];

			if (isset($this->params['DISCONNECT_TIME'])) {
				$dataToPost['disconnect_time'] = $this->params['DISCONNECT_TIME'];
			}
			if (isset($this->params['CONNECT_TIME'])) {
				$dataToPost['connect_time'] = $this->params['CONNECT_TIME'];
			}
		}
		if (strtoupper($this->params['MSG_TYPE']) == "CANCEL_PUBLISH") {
			$dataToPost['donor'] = $this->params['DONOR'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "PUBLISH_RESPONSE") {
			$dataToPost['approval_ind'] = $this->params['APPROVAL_IND'];
			if (isset($this->params['ROUTE_TIME'])) {
				$dataToPost['route_time'] = Application_Model_General::getDateTimeInSqlFormat($this->params['ROUTE_TIME']);
			}
		}
		if (strtoupper($this->params['MSG_TYPE']) == "CANCEL_PUBLISH_RESPONSE") {
			$dataToPost['msgType'] = "Cancel_Publish_response";

			$dataToPost['approval_ind'] = $this->params['APPROVAL_IND'];
			$dataToPost['route_time'] = Application_Model_General::getDateTimeInSqlFormat($this->params['ROUTE_TIME']);
		}
		if (strtoupper($this->params['MSG_TYPE']) == "INQUIRE_NUMBER_RESPONSE") {
			$dataToPost['approval_ind'] = $this->params['APPROVAL_IND'];
			$dataToPost['current_operator'] = $this->params['CURRENT_OPERATOR'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "DB_SYNCH_REQUEST") {
			$dataToPost['from_date'] = $this->params['FROM_DATE'];
			$dataToPost['to_date'] = $this->params['TO_DATE'];
		}
		if (strtoupper($this->params['MSG_TYPE']) == "DB_SYNCH_RESPONSE") {
			$dataToPost['file_name'] = $this->params['FILE_NAME'];
		}
		$client = new Zend_Http_Client();
		$url = Application_Model_General::getSettings('UrlToInternalResponse');
		$client->setUri($url);
		$client->setParameterGet('method', $this->getMethodName($this->params['MSG_TYPE']));
		//TODO: take the const to configuration
		$client->setConfig(array('timeout' => 300));
		$client->setParameterPost($dataToPost);
		$client->setAuth('free', 'free'); //CR CHANGE Password
		$client->setMethod(Zend_Http_Client::POST);
		$response = $client->request();
		$ret = $response->getBody();
		return $ret;
	}

	/**
	 * Gets matching Function Call for Message Type
	 * 
	 * @param type $msg_type
	 * @return string 
	 */
	protected function getMethodName($msg_type) {
		switch ($msg_type) {
			case 'Check':
			case 'Check_response':
				$method = 'check_transfer_availbility';
				break;
			case 'Request':
			case 'Request_response':
				$method = 'transfer_request';
				break;
			case 'Update':
			case 'Update_response':
				$method = 'update_transfer_request';
				break;
			case 'Cancel':
			case 'Cancel_response':
				$method = 'cancel_transfer_request';
				break;
			case 'Execute':
			case 'Execute_response':
				$method = 'execute_transfer';
				break;
			case 'Publish':
			case 'Publish_response':
				$method = 'publish';
				break;
			case "Cancel_publish":
			case "Cancel_publish_response":
				$method = 'cancel_publish';
				break;
			case "Return":
			case "Return_response":
				$method = 'return';
				break;
			case "Inquire_number":
			case "Inquire_number_response":
				$method = 'inquire_number';
				break;
			case "Up_system":
				$method = 'up_system';
				break;
			case "Down_system":
				$method = 'down_system';
				break;
			default:
				$method = null;
				break;
		}
		return $method;
	}

	/**
	 * sends automatic response to transaction messages sent to internal.
	 * 
	 * @param bool $status
	 */
	public function CreateMethodResponse($status) {
		//update DB ack!
		//SEND RESPONSE TO PROVIDER
		$response = array(
			'REQUEST_ID' => $this->params['REQUEST_ID'],
			'PROCESS_TYPE' => $this->params['PROCESS_TYPE'],
			'MSG_TYPE' => $this->params['MSG_TYPE'] . "_response",
			'REQUEST_TRX_NO' => $this->params['TRX_NO'],
			'FROM' => $this->params['TO'],
			'TO' => $this->params['FROM'],
			'REQUEST_RETRY_DATE' => $this->params['RETRY_DATE'],
			'RETRY_DATE' => Application_Model_General::getDateIso(),
			'RETRY_NO' => $this->params['RETRY_NO'],
			'VERSION_NO' => Application_Model_General::getSettings("VersionNo"),
			'NETWORK_TYPE' => Application_Model_General::getSettings('NetworkType'),
			'NUMBER_TYPE' => "I", //@TODO take from config
		);
		$number = array("NUMBER" => $this->params['NUMBER']);
		Application_Model_General::writeToLog(array_merge($response, $number));
		//check $status 
		if (($status->status == "Ack00" && !isset($status->resultCode)) || ($status->status == NULL && $status->resultCode == "Ack00")) {
			$response['APPROVAL_IND'] = "Y";
		} elseif ($status->status == "Gen07") {
			$response['APPROVAL_IND'] = "N";
			$response['REJECT_REASON_CODE'] = "Gen07";
		} else {
			$response['APPROVAL_IND'] = "N";
			$response['REJECT_REASON_CODE'] = $status->status;
		}
		if ($this->params['MSG_TYPE'] == "Execute") {
			$time = isset($status->DISCONNECT_TIME) ? $status->DISCONNECT_TIME : null;
			$response['DISCONNECT_TIME'] = Application_Model_General::getDateIso($time);
		}
		if ($response['MSG_TYPE'] == "Inquire_number_response") {

//			$response['APPROVAL_IND'] = $status->approval_ind;
			$response['CURRENT_OPERATOR'] = $status->current_operator;
		}
		if (isset($status->resultCode) && !empty($status->resultCode)) {
			$response['REJECT_REASON_CODE'] = $status->resultCode;
		}
		$reqModel = new Application_Model_Request($response);
		$reqModel->ExecuteFromInternal(FALSE); // ExecuteResponse();
	}

}
