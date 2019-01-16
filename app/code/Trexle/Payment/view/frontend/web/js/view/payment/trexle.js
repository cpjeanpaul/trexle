define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'trexle_payment',
                component: 'Trexle_Payment/js/view/payment/method-renderer/trexle'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
