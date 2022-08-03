<?php
namespace Custom\Test\Block;

class Index extends \Magento\Framework\View\Element\Template
{
	public function test() {
		return "This is my first module";
	}

	public function hello() {
		return "Hi Everyone";
	}
}
?>