<?php

 
namespace BronzeByte\CustomOrderNumber\Plugin\Model\Quote\ResourceModel;

use Magento\Quote\Model\ResourceModel\Quote;

class QuotePlugin
{
    protected $incrementIdGenerator;
    public function __construct(
        \BronzeByte\CustomOrderNumber\Helper\Generator $incrementIdGenerator
    ) {
        $this->incrementIdGenerator = $incrementIdGenerator;
    }

    public function aroundGetReservedOrderId(Quote $subject, \Closure $proceed, $quote)
    {
        $originalSequence = $proceed($quote); 
        $incrementId = $this->incrementIdGenerator->generateIncrementId(
            $quote,
            \Magento\Sales\Model\Order::ENTITY,
            $originalSequence 
        );
        return $incrementId;
    }
}
