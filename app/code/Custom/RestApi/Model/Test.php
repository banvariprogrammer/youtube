<?php
namespace Custom\RestApi\Model;

class Test implements \Custom\RestApi\Api\RestapiInterface {
	
	public function test($data){

		$result = json_decode($data);

		print_r($result); die;

	}
}

?>