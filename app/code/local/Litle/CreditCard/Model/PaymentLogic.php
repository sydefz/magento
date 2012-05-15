<?php
require_once('Litle/LitleSDK/LitleOnline.php');

class Litle_CreditCard_Model_PaymentLogic extends Mage_Payment_Model_Method_Cc
{
	/**
	 * unique internal payment method identifier
	 */
	protected $_code = 'creditcard';

	/**
	 * this should probably be true if you're using this
	 * method to take payments
	 */
	protected $_isGateway               = true;

	/**
	 * can this method authorise?
	 */
	protected $_canAuthorize            = true;

	/**
	 * can this method capture funds?
	 */
	protected $_canCapture              = true;

	/**
	 * can we capture only partial amounts?
	 */
	protected $_canCapturePartial       = true;

	/**
	 * can this method refund?
	 */
	protected $_canRefund               = true;

	protected $_canRefundInvoicePartial		= true;

	/**
	 * can this method void transactions?
	 */
	protected $_canVoid                 = true;

	/**
	 * can admins use this payment method?
	 */
	protected $_canUseInternal          = true;

	/**
	 * show this method on the checkout page
	 */
	protected $_canUseCheckout          = true;

	/**
	 * available for multi shipping checkouts?
	 */
	protected $_canUseForMultishipping  = true;

	/**
	 * can this method save cc info for later use?
	 */
	protected $_canSaveCc = false;


	public function getConfigData($fieldToLookFor, $store = NULL)
	{
		$returnFromThisModel = Mage::getStoreConfig('payment/CreditCard/' . $fieldToLookFor);
		if( $returnFromThisModel == NULL )
		$returnFromThisModel = parent::getConfigData($fieldToLookFor, $store);

		return $returnFromThisModel;
	}

	public function getCreditCardInfo(Varien_Object $payment)
	{
		$retArray = array();
		$retArray["type"] = $payment->getCcType();
		$retArray["number"] = $payment->getCcNumber();
		preg_match("/\d\d(\d\d)/", $payment->getCcExpYear(), $expYear);
		$retArray["expDate"] = sprintf('%02d%02d', $payment->getCcExpMonth(), $expYear[1]);
		$retArray["cardValidationNum"] = $payment->getCcCid();

		return $retArray;
	}

	public function getContactInformation($contactInfo)
	{
		if(!empty($contactInfo)){
			$retArray = array();
			$retArray["firstName"] =$contactInfo->getFirstname();
			$retArray["lastName"] = $contactInfo->getLastname();
			$retArray["companyName"] = $contactInfo->getCompany();
			$retArray["addressLine1"] = $contactInfo->getStreet(1);
			$retArray["addressLine2"] = $contactInfo->getStreet(2);
			$retArray["addressLine3"] = $contactInfo->getStreet(3);
			$retArray["city"] = $contactInfo->getCity();
			$retArray["state"] = $contactInfo->getRegion();
			$retArray["zip"] = $contactInfo->getPostcode();
			$retArray["country"] = $contactInfo->getCountry();
			$retArray["email"] = $contactInfo->getCustomerEmail();
			$retArray["phone"] = $contactInfo->getTelephone();
			return $retArray;
		}
		return NULL;
	}


	public function getBillToAddress(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if(!empty($order)){
			$billing = $order ->getBillingAddress();
			if(!empty($billing)){
				return $this->getContactInformation($billing);
			}
		}
		return NULL;
	}

	public function getShipToAddress(Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if(!empty($order)){
			$shipping = $order->getShippingAddress();
			if(!empty($shipping)){
				return $this->getContactInformation($shipping);
			}
		}
		return NULL;
	}

	public function merchantData(Varien_Object $payment)
	{
		$hash = array('user'=> $this->getConfigData("user"),
 					'password'=> $this->getConfigData("password"),
					'merchantId'=>$this->getConfigData("merchant_id"),
					'version'=>'8.10',
					'reportGroup'=>$this->getConfigData("reportGroup"),
					'url'=>$this->getConfigData("url"),	
					'proxy'=>$this->getConfigData("proxy"),
					'timeout'=>$this->getConfigData("timeout")
		);
		return $hash;
	}

public function processResponse(Varien_Object $payment,$litleResponse){
		$message = XmlParser::getAttribute($litleResponse,'litleOnlineResponse','message');
		if ($message == "Valid Format"){
			$isSale = ($payment->getCcTransId() != NULL)? FALSE : TRUE;
			if( isset($litleResponse))
			{
				$litleResponseCode = XMLParser::getNode($litleResponse,'response');
				if($litleResponseCode != "000")
				{
					$payment
					->setStatus("Rejected")
					->setCcTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setLastTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setTransactionId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setIsTransactionClosed(0)
					->setTransactionAdditionalInfo(XMLParser::getNode($litleResponse,'message'));
					
					if($isSale)
						throw new Mage_Payment_Model_Info_Exception(Mage::helper('core')->__("Transaction was not approved. Contact us or try again later."));
					else
						throw new Mage_Payment_Model_Info_Exception(Mage::helper('core')->__("Transaction was not approved. Contact Litle or try again later."));
				}
				else
				{
					$payment
					->setStatus("Approved")
					->setCcTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setLastTransId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setTransactionId(XMLParser::getNode($litleResponse,'litleTxnId'))
					->setIsTransactionClosed(0)
					->setTransactionAdditionalInfo(XMLParser::getNode($litleResponse,'message'));
				}
				return $this;
			}
		}
		else{
			Mage::throwException($message);
		}
	}
	/**
	 * this method is called if we are just authorising
	 * a transaction
	 */
	public function authorize(Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();
		$orderId =  $order->getIncrementId();
		$amountToPass = ($amount* 100);

		if (!empty($order)){
			$hash = array(
	 					'orderId'=> $orderId,
	 					'amount'=> $amountToPass,
	 					'orderSource'=> "ecommerce",
						'billToAddress'=> $this->getBillToAddress($payment),
						'shipToAddress'=> $this->getAddressInfo($payment),
	 					'card'=> $this->getCreditCardInfo($payment)
			);
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
			$litleResponse = $litleRequest->authorizationRequest($hash_in);
			$this->processResponse($payment,$litleResponse);
		}
	}

	/**
	 * this method is called if we are authorising AND
	 * capturing a transaction
	 */
	public function capture (Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();
		if (!empty($order)){
			
			$orderId =$order->getIncrementId();
			$amountToPass = ($amount* 100);
			$isPartialCapture = ($amount < $order->getGrandTotal()) ? "true" : "false";
			$isSale = ($payment->getCcTransId() != NULL)? FALSE : TRUE;
			
			if( !$isSale )
			{
				$hash = array(
								'litleTxnId' => $payment->getParentTransactionId(),//getCcTransId(),
								'amount' => $amountToPass,
								'partial' => $isPartialCapture
				);
			} else {
				$hash = array(
			 					'orderId'=> $orderId,
			 					'amount'=> $amountToPass,
			 					'orderSource'=> "ecommerce",
								'billToAddress'=> $this->getBillToAddress($payment),
								'shipToAddress'=> $this->getAddressInfo($payment),
			 					'card'=> $this->getCreditCardInfo($payment)
				);
			}
				
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
				
			if( $isSale )
			{
				$litleResponse = $litleRequest->saleRequest($hash_in);
			} else {
				$litleResponse = $litleRequest->captureRequest($hash_in);
			}
		}
		$this->processResponse($payment,$litleResponse);
	}

	/**
	 * called if refunding
	 */
	public function refund (Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();
		$amountToPass = ($amount* 100);
		if (!empty($order)){
			$hash = array(
						'litleTxnId' => $payment->getCcTransId(),
						'amount' => $amountToPass
			);
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
			$litleResponse = $litleRequest->creditRequest($hash_in);
		}
		$this->processResponse($payment,$litleResponse);
		return $this;
	}

	/**
	 * called if voiding a payment
	 */
	public function void (Varien_Object $payment)
	{
		$order = $payment->getOrder();
		if (!empty($order)){
			$hash = array(
						'litleTxnId' => $payment->getCcTransId()
			);
			$merchantData = $this->merchantData($payment);
			$hash_in = array_merge($hash,$merchantData);
			$litleRequest = new LitleOnlineRequest();
			$litleResponse = $litleRequest->voidRequest($hash_in);
		}
		$this->processResponse($payment,$litleResponse);
	}
}
