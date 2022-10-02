<?php
	namespace Banvari\Commands\Console\Command;
	
	use Symfony\Component\Console\Command\Command;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Output\OutputInterface;
	use Symfony\Component\Console\Input\InputOption;
	
	class test extends Command
	{
		private $productRepository;
		
		
		public function __construct(
		    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
		) {
		    $this->productRepository = $productRepository;
		    
		    parent::__construct();
		}
		
		protected function configure() {
			$this->setName('banvari:test')->setDescription('only for testing');
			$this->setDefinition($this->getInputList());
			parent::configure();
		}
		
		protected function execute(InputInterface $input, OutputInterface $output) {
		
			$sku = $input->getOption('product_sku');
			
			if(!empty($sku)) {
				$product = $this->productRepository->get($sku);
				
				echo json_encode($product->getData());die;
			} else {
			echo "Please enter product SKU in --product_sku key";die;
			}
		
			
		}
		
		
		public function getInputList(){
		$sku = [];
		$sku[] = new InputOption('product_sku', null, InputOption:: VALUE_OPTIONAL, 'Product SKU');
		return $sku;
		}
	}
?>
