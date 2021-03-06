<?php

/**
 * Request Model
 * Model for Number Transaction operations.
 * 
 * @package ApplicationModel
 * @subpackage RequestModel
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Request Object
 * 
 * @package ApplicationModel
 * @subpackage RequestModel
 */
class Application_Model_Request {

	/**
	 *
	 * @var object $request the instance of the request object from Np_Method
	 */
	protected $request;

	/**
	 *
	 * @var array $data  an array of message params
	 */
	protected $data;

	/**
	 * Constructor
	 * 
	 * Checks if message is soap or not 
	 * 
	 * @param array $params
	 * @return void 
	 */
	public function __construct($params) {
		//check if this is a soap request
		if (isset($params['SOAP'])) {

			//from provider
			//if does not have a number get it from request_id - for sending to internal
			if (!isset($params['NUMBER']) && ($params['MSG_TYPE'] != "Check") && isset($params['REQUEST_ID'])) {

				$params['NUMBER'] = Application_Model_General::getFieldFromRequests("number", $params['REQUEST_ID']);
			}

			$this->data = $params;
		} else if ($params) {
			// we are receiving data => need to send to provider
			if (!isset($params['REQUEST_ID']) && ($params['MSG_TYPE'] != "Check") && isset($params['NUMBER'])) {
				//CALL FROM INTERNAL WITH NO REQUEST ID
				//if the type is check the request id will be added later- on insert to DB
				$params['REQUEST_ID'] = Application_Model_General::getFieldFromRequests("request_id", $params['NUMBER']);
			}
			$this->data = $params;
		} else {
			//no parameters
			$this->data = NULL;
			$this->request = NULL;
			return;
		}

		$this->request = Np_Method::getInstance($this->data);
	}

	/**
	 * checks if PreValidate Passed
	 * 
	 * @return BOOL 
	 */
	protected function RequestValidate() {
		//each Method implements Validate
		return isset($this->request) && $this->request->PreValidate();
	}

	/**
	 * If "Publish response" 
	 * saves to transactions table then sends to internal
	 * if other response 
	 * sends to internal without saving to transactions
	 * if not response 
	 * Sends Params to provider/ineternal via GET in New Process 
	 * 
	 * @return BOOL 
	 */
	public function Execute() {
		if (TRUE === ($requestValidate = $this->RequestValidate())) {
			$msg_type = explode("_", $this->request->getHeaderField("MSG_TYPE"));
			if (isset($msg_type[1]) && $msg_type[1] == "response") {
				// if is response 
				if ($msg_type[0] == "Publish") {
					//if is publish response
					//don't pass to Internal - it is saved and taking care of in our system

					$this->request->setAck("Ack00");
//                    $this->ExecuteResponse();
				}
				$this->ExecuteResponse();
			} elseif (count($msg_type) > 1 && $msg_type[1] == 'publish' && $msg_type[2] == 'response') {
				// if is cancel publish response
//				$this->saveTransactionsDB();
				$this->ExecuteResponse();
			} elseif ($this->request->getHeaderField("MSG_TYPE") == "Inquire_number_response") {
				// if is inquire publish response
				$this->request->setAck("Ack00");
				$this->ExecuteResponse();
			} else {
				//if not response
				if (Application_Model_General::forkProcess("/provider/internal", $this->data)) {
					// create new process for not responses
					$someAck = $this->request->getAck();
					$this->request->setAck("Ack00");
				} else {
					$this->request->setAck("Ack01");
				}
			}
		} else {
			// if doesnt validate
//			$this->saveTransactionsDB();
		}
//		$ret = $this->request->getAck();
		// TODO oc666: not required for all cases (set ack as reject reason code)
		return $this->request->setRejectReasonCode($this->request->getAck());
//		return $this->request->getAck();
	}

	/**
	 * checks PostValidate()
	 * if TRUE
	 * saves data to db.
	 * send request to internal and returns response ack.
	 * if FALSE 
	 * puts FALSE in ACK
	 * send response via CreateMethodResponse
	 */
	public function ExecuteRequest() {
		$validate = $this->request->PostValidate();
		$request = new Application_Model_Internal($this->data);
		if ($validate == TRUE) {
			$this->saveDB();
			$ack = $this->request->getAck();

			$content = $request->SendRequestToInternal($ack, $this->request->getRejectReasonCode(), $this->request->getIDValue());
			if (empty($content)) {
				Application_Model_General::logRequest($this->request->getHeaderField('REQUEST_ID'), "ExecuteRequest: Internal CRM error");
				return FALSE;
			}
			$response = Zend_Json::decode($content, Zend_Json::TYPE_OBJECT);
			if (!isset($response->status)) {
				$response->status = "";
			} elseif ($response->status == "FALSE") {
				$response->status = "";
			}
		} else {
//			$this->saveTransactionsDB();
			if ($validate === FALSE) {
				$validate = "Ack01";
			}
			$response = new stdClass();
			$response->status = $validate;
		}

		$request->CreateMethodResponse($response);
	}

	/**
	 * checks PostValidate()
	 * if TRUE checks if request type is execute response
	 * 			if it is updates ack in db
	 * 			saves data to db.
	 * 			send request to internal and returns response ack.
	 * if FALSE 
	 * 		puts FALSE in ACK
	 * @return void 
	 */
	public function ExecuteResponse() {
//		Application_Model_General::writeToLog($this->data);
		$validate = $this->request->PostValidate();
		if ($validate === true) {
			$some = $this->request->getAck();

			$this->request->setCorrectAck();
			$this->saveDB();
//            $this->saveTransactionsDB();
			$sendToInternal = new Application_Model_Internal($this->data);
			$some = $this->request->getAck();

			$ack = json_decode($sendToInternal->SendRequestToInternal($this->request->getAck(), $this->request->getRejectReasonCode(), $this->request->getIDValue()));
			$reject_reason_code = $this->request->getRejectReasonCode();
			if (empty($reject_reason_code)) {
				$sendToInternal->sendAutoRequest($this->request->type);
				$ret = "Ack00";
			}

			if (isset($ack->resultCode) && $ack->resultCode !== "OK") {
				$ret = $ack->resultCode;
			} else {
				if (!isset($ack->resultCode)) {
					$ret = "Ack00";
				}
			}
		} else {
//			$this->saveTransactionsDB();
			if ($validate !== FALSE) {

				$ret = $validate;
			}
		}
		$this->request->setAck($ret);
	}

	/**
	 * internal send SOAP
	 * check InternalPostValidate()
	 * if true saves to db
	 * sends internal soap
	 * TODO: CR
	 */
	public function ExecuteFromInternal($verifyInternal = TRUE) {
		if ($this->data['MSG_TYPE'] == "Publish" || $this->data['MSG_TYPE'] == "Execute") {
			// @TODO oc666: check this condition 
			if (substr($this->data['REQUEST_ID'], 4, 2) == $this->data['FROM'] || $this->data['MSG_TYPE'] == "Execute") {
				$this->saveDB();
			} else {
				$this->saveTransactionsDB();
			}

			if (!isset($this->data['NUMBER']) || $this->data['NUMBER'] == NULL) {
				$this->data['NUMBER'] = Application_Model_General::getFieldFromRequests('number', $this->data['REQUEST_ID']);
			} else if ($this->data['MSG_TYPE'] != "Publish") {
//				Application_Model_General::writeToLog($this->data);
			}
		}
		$validate = false;
		$this->request->setAck('Ack01'); //??
		if (!$verifyInternal || $this->request->InternalPostValidate()) {
			$this->request->setCorrectAck();

			if ($this->data['MSG_TYPE'] != "Publish" && $this->data['MSG_TYPE'] != "Execute") {
				$this->saveDB();
			}
			$validate = true;
		}
		$this->createResponse($validate); //create soap response and send it to provider
	}

	/**
	 * Saves to Transactions and Requests tables
	 * 
	 */
	protected function saveDB() {
		$this->request->saveToDB();
		$this->saveTransactionsDB();
	}

	/**
	 * saveTransactionDB saves data to Transactions table in db
	 * @return bool db Success or Failure 
	 */
	protected function saveTransactionsDB($TRX = FALSE) {
		$tbl = new Application_Model_DbTable_Transactions(Np_Db::master());
		if ($TRX != FALSE) {
			$trxNo = $TRX;
		} else {
			$trxNo = $this->request->getHeaderField("TRX_NO");
		}

		$msgType = $this->request->getHeaderField("MSG_TYPE");
		$portTime = $this->request->getBodyField('PORT_TIME');
		$rejectReasonCode = $this->request->getRejectReasonCode();

		$reqId = $this->request->getHeaderField('REQUEST_ID');
		if ($trxNo) {
			$data = array(
				'trx_no' => $trxNo,
				'request_id' => $reqId,
				'message_type' => $msgType,
				'ack_code' => $this->request->getAck(),
				'target' => $this->request->getHeaderField("TO")
			);
			if (!$rejectReasonCode || $rejectReasonCode === NULL || $rejectReasonCode == "") {
				// do nothing
			} else {
				$data['reject_reason_code'] = $rejectReasonCode;
			}
			if ($msgType == "Update" || $msgType == "Request") {
				$data['requested_transfer_time'] = Application_Model_General::getTimeInSqlFormatFlip($portTime);
			}
			if ($msgType == "Publish") {
				$data['donor'] = Application_Model_General::getDonorByReqId($reqId);
			}

			$res = $tbl->insert($data);

			return $res;
		} else {
			//this request if from internal - have to add trx_no
			//save to Transactions table trx_no  has to be consisten to id of the table
			$adapter = $tbl->getAdapter();
			$adapter->beginTransaction();
			try {
				$_id = $tbl->insert(array());
				$id = substr("00000000000" . $_id, -12, 12);
				$trx_no = Application_Model_General::getSettings('InternalProvider');
				$trx_no = $trx_no . $id;
				$this->request->setTrxNo($trx_no);
				if (isset($this->data['request_id'])) {
					$reqId = $this->data['request_id'];
				}
				$row_insert = array(
					'trx_no' => $trx_no,
					'request_id' => $reqId,
					'message_type' => $this->request->getHeaderField("MSG_TYPE"),
					'ack_code' => $this->request->getAck(),
					'target' => $this->request->getHeaderField("TO")
				);
				if (!$rejectReasonCode || $rejectReasonCode === NULL || $rejectReasonCode == "") {
					// do nothing
				} else {
					$row_insert['reject_reason_code'] = $rejectReasonCode;
				}
				if ($msgType == "Update" || $msgType == "Request" ||
					($msgType == "Check" && Application_Model_General::isAutoCheck($reqId))) {
					$row_insert['requested_transfer_time'] = Application_Model_General::getTimeInSqlFormat($portTime);
				}
				if ($msgType == "Publish") {

					$row_insert['donor'] = Application_Model_General::getDonorByReqId($reqId);
				}
				$res = $tbl->update($row_insert, "id = " . $_id);
				$adapter->commit();
				return true;
			} catch (Exception $e) {
				error_log("the reason for the error GT : " . print_r($e, 1));
				$adapter->rollBack();
				return false;
			}
		}
	}

	/**
	 * If Status TRUE update request in requests table TRUE
	 * @param bool $status 
	 * @TODO need to clear out the function that calling this function to 
	 * 		use the right value of the argument. For example when
	 * 		For example when request get reject reason code need to input FALSE
	 * 		check: calling from executeReponse method
	 */
	protected function updateDB_ack($status) {
		//$status check?
		if ($status) {
			$result = Application_Model_General::updateRequests(
					$this->request->getHeaderField("REQUEST_ID"), $this->request->getHeaderField("MSG_TYPE"));
		} else {
			$result = Application_Model_General::updateRequests(
					$this->request->getHeaderField("REQUEST_ID"), $this->request->getHeaderField("MSG_TYPE"), 0);
		}
	}

	function getFieldIfExists($param, $getFrom = "BODY") {

		if ($getFrom == "BODY") {
			$retParam = $this->request->getBodyField($param);
		} else {
			$retParam = $this->request->getHeaderField($param);
		}

		if (isset($retParam)) {
			$res = $retParam;
		} else {
			$res = NULL;
		}
		return $res;
	}

	/**
	 * Makes XML for SOAP Response Body
	 *  
	 */
	protected function setResponseXmlBody() {

		$xml = $this->request->xml();
		$xmlString = $xml->asXML();
		$dom = new DOMDocument();
		$dom->loadXML($xmlString);

		$isValid = $dom->schemaValidate('npMessageBody.xsd');
		if ($isValid === TRUE) {
			return $xmlString;
		} else {
			error_log("xml doesn't validate");
		}
	}

	/**
	 * 
	 * Internal's Soap Client Used to send Soap to Provider
	 * If "QA" set for provider it will return demi-value of true (bool)
	 * 	
	 * @return object SOAP result  
	 */
	protected function sendArray() {
		$request_id = $this->request->getHeaderField("REQUEST_ID");
		$lastTransaction = $this->request->getHeaderField("MSG_TYPE");
		$from = $message_recipient_code = $this->request->getHeaderField("TO");
		if (strtoupper($lastTransaction) == "DOWN_SYSTEM") {
			Application_Model_General::saveShutDownDetails("GT", "DOWN");
		} elseif (strtoupper($lastTransaction) == "UP_SYSTEM") {
			Application_Model_General::saveShutDownDetails("GT", "UP");
		}

		// TODO oc666 : Need spec for this section

		$message_recipient_code = $this->request->getHeaderField("TO");
//		$request_id = $this->request->getHeaderField("REQUEST_ID");

		if (strtoupper($lastTransaction) == "PUBLISH" && strtoupper($message_recipient_code) != "XX") {
			$url = $this->getRecipientUrl();
			$trx_no = $this->request->getHeaderField("TRX_NO");
			$request_id = $this->request->getHeaderField("REQUEST_ID");
//			$explodethis = substr($request_id, 4, -15);
//			$explode_id = explode($explodethis, $request_id);
//			$request_id = implode($message_recipient_code, $explode_id);
//			$this->request->setHeader("REQUEST_ID", $request_id);
			// IN ORDER TO SAVE THE PUBLISHES TO EACH PROVIDER   
			// TODO oc666: why need to save transaction here?
			$client = $this->getSoapClient($url);
			$ret = $this->sendAgain($client);
//			return $ret;
		}
		if (strtoupper($lastTransaction) == "PUBLISH" && strtoupper($message_recipient_code) == "XX") {
			$request_id = $this->request->getHeaderField("REQUEST_ID");

			$tbl = new Application_Model_DbTable_Requests(Np_Db::slave());
			$select = $tbl->select();
			$select->where('request_id = ?', $request_id);
			$result = $select->query();
			$row = $result->fetch();
			$row['forceAll'] = 1;
			$cmd = "/cron/checkpublish";
			Application_Model_General::forkProcess($cmd, $row); // for debugging
			return true;
//			$providers = Application_Model_General::getSettings('provider');
//			foreach ($providers as $provider => $value) {
////				$request_id = $this->request->getHeaderField("REQUEST_ID");
//				$explodethis = substr($request_id, 4, -15);
//				$explode_id = explode($explodethis, $request_id);
//				$request_id = implode($provider, $explode_id);
//				$this->request->setHeader("TO", $provider);
//				$this->request->setHeader("REQUEST_ID", $request_id);
//				$url = $value;
//				$this->saveTransactionsDB();
//
//				$client = $this->getSoapClient($url);
//				$ret = $this->sendAgain($client);
//			}
		} else {
			$url = $this->getRecipientUrl();
			if ($url == "QA") {
				$soapAckCode = "undefined";
				Application_Model_General::updateSoapAckCode($soapAckCode, $request_id, $lastTransaction);
				return true; //for QA 
			}

			if (strtoupper($lastTransaction) != "PUBLISH" && strtoupper($message_recipient_code) != "XX") {
				$client = $this->getSoapClient($url);
				$ret = $this->sendSoap($client);
			}
		}


		if (!isset($ret) || !isset($ret->NP_ACK->ACK_CODE)) {
			return false;
		}
		$soapAckCode = $ret->NP_ACK->ACK_CODE;
		$request_id = $this->request->getHeaderField("REQUEST_ID");
		Application_Model_General::updateSoapAckCode($soapAckCode, $request_id, $lastTransaction, $this->request->getHeaderField("TRX_NO"));
		return $ret;
	}

	// TODO oc666: require spec
	function sendAgain($client) {
		$timeout = 5;
		$ret = $this->sendSoap($client);
		$tries = 1;
		sleep($timeout);
		while (!isset($ret->NP_ACK)) {
			$ret = $this->sendSoap($client);
			sleep($timeout);
			$tries++;
			if ($tries == 3) {
				error_log($tries . " tries have failed.");
				break;
			}
		}
		return $ret;
	}

	function getSoapClient($url) {
		$client = new Zend_Soap_Client(
				Application_Model_General::getWsdl(),
				array(
					'uri' => $url,
					'location' => $url,
					'soap_version' => SOAP_1_1,
					'encoding' => 'UTF-8',
					'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_DEFLATE
				)
		);
		return $client;
	}

	function sendSoap($client) {
		try {
			$soapArray = $this->responseArray();

			$trxInc = (int) substr($soapArray['NP_MESSAGE']['HEADER']['TRX_NO'], 3);
			// TODO oc666: why we need this??
//			$trxInc = $trxInc + 1;
//			$trxIncLen = strlen($trxInc);

			$formatted = sprintf("%012d", $trxInc);
			$newTrxNo = substr($soapArray['NP_MESSAGE']['HEADER']['TRX_NO'], 0, 2) . $formatted;
			$soapArray['NP_MESSAGE']['HEADER']['TRX_NO'] = $newTrxNo;
			$retryNo = Application_Model_General::checkIfRetry($this->request->getHeaderField('REQUEST_ID'), $this->request->getHeaderField('MSG_TYPE'));
			if (!empty($retryNo)) {
				$soapArray['NP_MESSAGE']['HEADER']['RETRY_NO'] = $retryNo;
			}
			$ret = $client->sendMessage($soapArray);
			// log all sending calls
			if (Application_Model_General::getSettings('EnableRequestLog')) {
				Application_Model_General::logRequestResponse($soapArray, $ret, $this->request->getHeaderField("REQUEST_ID"), '[Output] ');
			}
		} catch (SoapFault $e) {
			$ret = FALSE;
		}
		return $ret;
	}

//	//@TODO change $data input to $this
//	/**
//	 * ResponseArray creates Array for Internal's SOAP Response
//	 * 
//	 * @return array $soapMsg 
//	 */
	protected function responseArray() {
		$soapMsg = array(
			'NP_MESSAGE' => array(
				'HEADER' => $this->request->getHeaders(),
				'BODY' => $this->setResponseXmlBody(),
			)
		);
		return $soapMsg;
	}

	/**
	 * Sends Internal's Response to Provider
	 *
	 * @param BOOL $status
	 * @return BOOL 
	 */
	public function createResponse($status) {
		if ($status) {
			$ack = $this->sendArray();
			if ($ack && isset($ack->NP_ACK) && isset($ack->NP_ACK->ACK_CODE)) {
				$ret = $ack->NP_ACK->ACK_CODE;
				$this->request->setAck($ret);
				//we need to see what validation needs to 
				//be here - ack from provider
				//update db - status OK
				if ($ack->NP_ACK->ACK_CODE == "Ack00") {
					Application_Model_General::updateTransactionsAck($this->request->getHeaderField('TRX_NO'), $ack->NP_ACK->ACK_CODE);
//					$this->updateDB_ack(true);
				} else {
//					$this->updateDB_ack(false);
				}
			} else {
				if (strtoupper($this->request->getHeaderField('MSG_TYPE')) == 'CHECK') {
					Application_Model_General::updateRequests($this->request->getHeaderField('REQUEST_ID'), $this->request->getHeaderField('MSG_TYPE'), 0);
				}
				Application_Model_General::updateTransactionsAck($this->request->getHeaderField('TRX_NO'), 'Err');
				return false;
			}
		} else {
//			$this->updateDB_ack(false);
			$response = new Application_Model_Internal($this->data);
			$response->SendErrorToInternal();
		}
		return true;
	}

	/**
	 * Get Provider RPC URL via "TO" field
	 * 
	 * @return string URL 
	 */
	protected function getRecipientUrl() {
		$providers = Application_Model_General::getSettings('provider');
		$key = $this->request->getHeaderField("TO");
		if (isset($providers[$key])) {
			return $providers[$key];
		}
		return FALSE;
	}

}
