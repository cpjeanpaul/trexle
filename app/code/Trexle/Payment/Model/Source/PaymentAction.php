<?php

namespace Trexle\Payment\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PaymentAction implements ArrayInterface
{

    const ACTION_AUTHORIZE = 'authorize';

    const ACTION_BOTH = 'authorize_capture';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ACTION_BOTH,
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}
