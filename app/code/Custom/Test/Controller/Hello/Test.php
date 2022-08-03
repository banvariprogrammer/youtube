<?php

namespace Custom\Test\Controller\Hello;

class Test extends \Magento\Framework\App\Action\Action
{

	private $pageFactory;

	public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    )
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        return $page;
    }
}
