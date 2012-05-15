<?php
class Baobaz_Arithmetic_IntegerController extends Mage_Core_Controller_Front_Action
{
	public function multiplyAction(){
		if($this->getRequest()->isPost()){
			$int1 = $this->getRequest()->getPost('int1');
			$int2 = $this->getRequest()->getPost('int2');
			$result = $int1 * $int2;
			Mage::getSingleton('customer/session')->addSuccess("$int1 * $int2 = $result");
		}
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
        $this->renderLayout();
	}
}