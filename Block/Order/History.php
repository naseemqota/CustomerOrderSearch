<?php

namespace Qota\CustomerOrderSearch\Block\Order;

use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
/**
 * Sales order history block
 */
class History extends \Magento\Sales\Block\Order\History
{
    protected $productRepository;
    protected $_storeManager;
    protected $_blockFactory;
    protected $_escaper;
    protected $orderCollectionFactory;
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
	    ProductRepositoryInterfaceFactory $productRepository,
	    \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Element\BlockFactory $blockFactory,
        \Magento\Framework\Escaper $_escaper,
        array $data = []
    ) {
	$this->productRepository = $productRepository;
	$this->_storeManager = $storeManager;
 	$this->_blockFactory = $blockFactory;
 	$this->_escaper = $_escaper;
 	$this->orderCollectionFactory = $orderCollectionFactory;
        parent::__construct($context, $orderCollectionFactory, $customerSession, $orderConfig,$data);
    }
    private function getOrderCollectionFactory()
    {
        if ($this->orderCollectionFactory === null) {
            $this->orderCollectionFactory = ObjectManager::getInstance()->get(CollectionFactoryInterface::class);
        }
        return $this->orderCollectionFactory;
    }

    /**
     * @return bool|\Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrderlist()
    {
       

      //  print_r($this->_escaper->escapeHtml(strip_tags($post['orderid'])));
        
        if (!($customerId = $this->_customerSession->getCustomerId())) {
            return false;
        }
        $post=$this->getRequest()->getParams();
       
        if (!$this->orders) {
                $this->orders = $this->getOrderCollectionFactory()->create($customerId)->addFieldToSelect(
                    '*'
                )->addFieldToFilter(
                    'status',
                    ['in' => $this->_orderConfig->getVisibleOnFrontStatuses()]
                )->setOrder(
                    'created_at',
                    'desc'
                );
            
        }else{

            if(isset($post)){
               if(isset($post['orderid'])){                
                    if($post['orderid']!=null){
                        $id=['eq'=>$post['orderid']];
                    }else{
                        $id=['neq' =>''];
                    }
               }else{
                    $id=['neq' =>''];
               }
               if(isset($post['from_date']) && ($post['to_date'])){

                    if(($post['from_date']!=null) && ($post['to_date']!=null)){
                         $date=['from' =>date("Y-m-d H:i:s",strtotime( $post['from_date'].' 00:00:00')),'to'=>date("Y-m-d H:i:s",strtotime( $post['to_date'].' 24:00:00'))];
                    }else{
                        $date=['neq' =>''];
                    }
                 
                }else{
                        $date=['neq' =>''];
                }

                if(isset($post['sku'])){
                   
                    if(($post['sku']!="")){
                        $this->orders = $this->getOrderCollectionFactory()->create($customerId)->join(
                            ["soi" => "sales_order_item"],
                        'main_table.entity_id = soi.order_id AND soi.product_type in ("simple","downloadable") ',
                        array('sku', 'name'))->addFieldToSelect(
                            '*'
                        )->addFieldToFilter(
                            'soi.sku',
                            ['eq'=>$post['sku']]
                        )->addFieldToFilter(
                            'main_table.increment_id',
                            $id
                        )->addFieldToFilter(
                            'main_table.created_at',
                            $date
                        )->setOrder(
                            'main_table.created_at',
                            'desc'
                        );

                    }else{

                        $this->orders = $this->getOrderCollectionFactory()->create($customerId)->addFieldToSelect(
                            '*'
                        )->addFieldToFilter(
                            'increment_id',
                            $id
                        )->addFieldToFilter(
                            'created_at',
                            $date
                        )->setOrder(
                            'created_at',
                            'desc'
                        );

                    }
                }else{
                    $date=['neq'=>'']; 
                }
            }

        }




        return $this->orders;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getOrderlist()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'sales.order.history.pagersearch'
            )->setCollection(
                $this->getOrderlist()
            );
            $this->setChild('pager', $pager);
            $this->getOrderlist()->load();
        }
        return $this;
    }

    public function getProductName($order){
        $items = $order->getAllItems();
        if($items){ 
            $total_qty = [];   
                foreach($items as $itemId => $_item){
                    $total_qty[][$itemId]= $_item->getName();
                }           
            $c=0;
            $html="<p>";
                foreach($total_qty as $itm){
                    $html.=($c+1).' ) '.$itm[$c].' <br/>';
                    $c++;
                }
            $html.="</p>";

            return $html;


        }
    }

    
}
