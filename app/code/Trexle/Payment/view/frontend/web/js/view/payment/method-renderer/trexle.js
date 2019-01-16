define([
        'jquery',
        'Magento_Payment/js/view/payment/cc-form'
    ],
    function ($, Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Trexle_Payment/payment/trexle'
            },

            context: function() {
                return this;
            },

            getCode: function() {
                return 'trexle_payment';
            },

            isActive: function() {
                return true;
            }
        });
    }
);