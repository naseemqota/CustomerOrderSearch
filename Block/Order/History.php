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
use Qota\CustomerOrderSearch\Api\FiltersInterface;

/**
 * Sales order history block
 */
class History extends \Magento\Sales\Block\Order\History
{
    protected $_storeManager;
    protected $_blockFactory;
    protected $_escape;
    protected $orderCollectionFactory;
    /**
     * @var FiltersInterface[]
     */
    private $specialFilter;

    /**
     * @param Context $context
     * @param CollectionFactory $orderCollectionFactory
     * @param Session $customerSession
     * @param Config $orderConfig
     * @param StoreManagerInterface $storeManager
     * @param BlockFactory $blockFactory
     * @param Escaper $escape
     * @param FiltersInterface[] $specialFilter
     * @param array $data
     */
    public function __construct(
        Context                           $context,
        CollectionFactory                 $orderCollectionFactory,
        Session                           $customerSession,
        Config                            $orderConfig,
        StoreManagerInterface             $storeManager,
        BlockFactory                      $blockFactory,
        Escaper                           $escape,
        array                             $specialFilter = [],
        array                             $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_blockFactory = $blockFactory;
        $this->_escape = $escape;
        $this->specialFilter = $specialFilter;
        $this->validate();
        $this->orderCollectionFactory = $orderCollectionFactory;

        parent::__construct(
            $context,
            $orderCollectionFactory,
            $customerSession,
            $orderConfig,
            $data
        );
    }

    /**
     * @return CollectionFactory
     */
    private function getOrderCollectionFactory(): CollectionFactory
    {
        return $this->orderCollectionFactory;
    }

    /**
     * @return Collection
     */
    public function getOrderList(): Collection
    {
        if (!($customerId = $this->_customerSession->getCustomerId())) {
            return false;
        }
        if (!$this->orders) {
            return $this->getOrderActive($customerId);
        } else {
            return $this->getOrderFilterList($customerId);
        }
        return false;
    }

    /**
     * @param $customerId
     * @return Collection
     */
    private function getOrderActive($customerId): Collection
    {
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

    /**
     * @param $customerId
     * @return Collection
     */
    private function getOrderFilterList($customerId): Collection
    {
        $post = $this->getRequest()->getParams();
        if (isset($post)) {
            $this->orders = $this->getOrderCollectionFactory()
                ->create($customerId);
            if (!empty($post['order_id'])) {
                $this->orders->addFieldToFilter(
                    'increment_id',
                    $post['order_id']
                );
            }

            $this->orders->addFieldToSelect('*');

            foreach ($this->specialFilter as $filters) {
                if ($filters->isFilterable($post)) {
                    $this->orders = $filters->filter($this->orders, $post);
                }
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
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getOrderList()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'sales.order.history.pagersearch'
            )->setCollection(
                $this->getOrderList()
            );
            $this->setChild('pager', $pager);
            $this->getOrderList()->load();
        }
        return $this;
    }

    /**
     * @param $order
     * @return string|void
     */
    public function getProductName($order)
    {
        $items = $order->getAllItems();
        if ($items) {
            $total_qty = [];
            foreach ($items as $itemId => $_item) {
                $total_qty[][$itemId] = $_item->getName();
            }
            $count = 0;
            $html = "<p>";
            foreach ($total_qty as $item) {
                $html .= $item[$count] . ' <br/>';
                $count++;
            }
            $html .= "</p>";
            return $html;
        }
    }

    /**
     * @param $order
     * @return string|void
     */
    public function getProductSku($order)
    {
        $items = $order->getAllItems();
        if ($items) {
            $total_qty = [];
            foreach ($items as $itemId => $item) {
                $total_qty[][$itemId] = $item->getSku();
            }
            $count = 0;
            $html = "<p>";
            foreach ($total_qty as $item) {
                $html .= ($count + 1) . ' ) ' . $item[$count] . ' <br/>';
                $count++;
            }
            $html .= "</p>";
            return $html;
        }
    }

    /**
     * @return void
     */
    private function validate(): void
    {
        foreach ($this->specialFilter as $specialFilter) {
            if (!$specialFilter instanceof FiltersInterface) {
                throw new InvalidArgumentException('Invalid object type.');
            }
        }
    }
}
