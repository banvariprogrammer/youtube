<?php
	namespace Banvari\Module\Controller\Adminhtml\Submenu;

	class Index extends \Magento\Backend\App\Action 
	{
		public function execute()
         {
            $this->_view->loadLayout();
            //$this->_view->getPage()->getConfig()->getTitle()->prepend(__('Sub menu'));
            $this->_view->renderLayout();
         }
	}
?>