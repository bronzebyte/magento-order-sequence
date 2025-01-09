<?php

 
namespace BronzeByte\CustomOrderNumber\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesOrderCreditmemoSaveBeforeObserver extends AbstractObserver implements ObserverInterface
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->updateIncrementId($observer->getCreditmemo(), self::TYPE_CREDITMEMO);
    }

    /**
     * @param $object \Magento\Sales\Model\Order
     * @return \Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection
     */
    public function getCollectionForOrder($object)
    {
        return $object->getCreditmemosCollection();
    }
}
