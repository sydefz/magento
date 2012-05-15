<?php

class BlackstoneMedia_ModuleName_IndexController extends Mage_Core_Controller_Front_Action {
	public function indexAction() {
		//print"testing";
		$this->loadLayout();
		$this->renderLayout();
	}

	public function submitAction() {
		if ($this->getRequest()->isPost()) {
			$int1 = $this->getRequest()->getPost('int1');
			$int2 = $this->getRequest()->getPost('int2');
			$result = $int1 * $int2;
			Mage::getSingleton('customer/session')->addSuccess("$int1 * $int2 = $result");
		}
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
	}
};

?>