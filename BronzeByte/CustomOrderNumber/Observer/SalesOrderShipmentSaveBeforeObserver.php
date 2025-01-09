<?php

 
namespace BronzeByte\CustomOrderNumber\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesOrderShipmentSaveBeforeObserver extends AbstractObserver implements ObserverInterface
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->updateIncrementId($observer->getShipment(), self::TYPE_SHIPMENT);
    }

    /**
     * @param $object \Magento\Sales\Model\Order
     * @return \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection
     */
    public function getCollectionForOrder($object)
    {
        return $object->getShipmentsCollection();
    }
}
