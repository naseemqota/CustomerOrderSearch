<?php
namespace Qota\CustomerOrderSearch\Block\Order;

use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

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
     * @param Context $context
     * @param CollectionFactory $orderCollectionFactory
     * @param Session $customerSession
     * @param Config $orderConfig
     * @param ProductRepositoryInterfaceFactory $productRepository
     * @param StoreManagerInterface $storeManager
     * @param BlockFactory $blockFactory
     * @param Escaper $_escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $orderCollectionFactory,
        Session $customerSession,
        Config $orderConfig,
	    ProductRepositoryInterfaceFactory $productRepository,
	    StoreManagerInterface $storeManager,
        BlockFactory $blockFactory,
        Escaper $_escaper,
        array $data = []
    ) {
	$this->productRepository = $productRepository;
	$this->_storeManager = $storeManager;
 	$this->_blockFactory = $blockFactory;
 	$this->_escaper = $_escaper;
 	$this->orderCollectionFactory = $orderCollectionFactory;

        parent::__construct(
            $context,
            $orderCollectionFactory,
            $customerSession,
            $orderConfig,
            $data
        );
    }
    private function getOrderCollectionFactory()
    {
        if ($this->orderCollectionFactory === null) {
            $this->orderCollectionFactory = ObjectManager::getInstance()
                ->get(CollectionFactoryInterface::class);
        }
        return $this->orderCollectionFactory;
    }

    /**
     * @return bool|Collection
     */
    public function getOrderlist()
    {
        if (!($customerId = $this->_customerSession->getCustomerId()))
        {
            return false;
        }
        if (!$this->orders)
        {
            $this->getOrderActive($customerId);
        }
        else
        {
            $this->getOrderFilterList($customerId);
        }
    }

    private function getOrderActive($customerId){

        $this->orders = $this->getOrderCollectionFactory()
            ->create($customerId)
            ->addFieldToSelect('*');
        $this->orders->addFieldToFilter(
            'status',
            ['in' => $this->_orderConfig->getVisibleOnFrontStatuses()]
        )->setOrder(
            'created_at',
            'desc'
        );

        return $this->orders;
    }

    private function getOrderFilterList($customerId)
    {
        $post = $this->getRequest()->getParams();
        if (isset($post))
        {
            $this->orders = $this->getOrderCollectionFactory()
                ->create($customerId);
            if (!empty($post['orderid']))
            {
                    $this->orders->addFieldToFilter(
                        'increment_id',
                        $post['orderid']
                    );
            }

            $this->orders->addFieldToSelect('*');

            if (!empty($post['sku']))
            {
                $this->orders->join(
                    ["soi" => "sales_order_item"],
                'main_table.entity_id = soi.order_id 
                AND 
                soi.product_type in ("simple","downloadable")',
                array('sku', 'name')
                )->addFieldToSelect('*')
                 ->addFieldToFilter( 'soi.sku', ['eq' => $post['sku']] );
            }
            else if (!empty($post['name']))
            {
                $this->orders->join(
                    ["soi" => "sales_order_item"],
                    'main_table.entity_id = soi.order_id 
                    AND 
                    soi.product_type in ("simple","downloadable")',
                    array('sku', 'name'))
                    ->addFieldToSelect('*')
                    ->addFieldToFilter( 'soi.name', ['like' => '%' . $post['name'] . '%']);
            }

            if (!empty($post['from_date']) && !empty($post['to_date']))
            {
                $date = ['from' => date("Y-m-d H:i:s", strtotime( $post['from_date'] . ' 00:00:00')),
                    'to' => date("Y-m-d H:i:s", strtotime( $post['to_date'] . ' 24:00:00')) ];
                $this->orders->addFieldToFilter(
                    'main_table.created_at',
                    $date
                );
            }
            else if (!empty($post['from_date']))
            {
                $this->orders->addFieldToFilter(
                    'main_table.created_at',
                    ['like' => date("Y-m-d ", strtotime($post['from_date'])) . '%']
                );
            }
            else if (!empty($post['to_date']))
            {
                $this->orders->addFieldToFilter(
                    'main_table.created_at',
                    ['like' => date("Y-m-d", strtotime($post['to_date'])) . '%']
                );
            }
            $this->orders->addFieldToFilter(
            'status',
            ['in' => $this->_orderConfig->getVisibleOnFrontStatuses()]
            )->setOrder(
                'created_at',
                'desc'
            );
        }
        return $this->orders;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getOrderlist())
        {
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

    public function getProductName($order)
    {
        $items = $order->getAllItems();
        if ($items)
        {
            $total_qty = [];
            foreach($items as $itemId => $_item){
                $total_qty[][$itemId] = $_item->getName();
            }
            $c = 0;
            $html = "<p>";
            foreach($total_qty as $itm)
            {
                $html .= ($c + 1) . ' ) ' . $itm[$c] . ' <br/>';
                $c++;
            }
            $html .= "</p>";
            return $html;
        }
    }

    public function getProductSku($order)
    {
        $items = $order->getAllItems();
        if ($items) {
            $total_qty = [];
            foreach($items as $itemId => $_item)
            {
                $total_qty[][$itemId] = $_item->getSku();
            }
            $c = 0;
            $html = "<p>";
                foreach ($total_qty as $itm)
                {
                    $html .= ($c + 1) . ' ) ' . $itm[$c] . ' <br/>';
                    $c++;
                }
            $html .= "</p>";
            return $html;
        }
    }
}
