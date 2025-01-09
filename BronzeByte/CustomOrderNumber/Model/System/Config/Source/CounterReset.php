<?php

 
namespace BronzeByte\CustomOrderNumber\Model\System\Config\Source;

class CounterReset implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('--- No automatic reset ---')],
            ['value' => 'daily', 'label' => __('Reset every day')],
            ['value' => 'monthly', 'label' => __('Reset every month')],
            ['value' => 'yearly', 'label' => __('Reset every year')]
        ];
    }
}
