/**
 * Copyright © Pronko Consulting (https://www.pronkoconsulting.com)
 * See LICENSE for the license details.
 */
/*browser:true*/
/*global define*/
define([
    'Magento_Checkout/js/view/payment/default',
    'jquery',
    'mage/url',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Payment/js/model/credit-card-validation/validator'
], function(Component, $,url) {
    'use strict';

    return Component.extend({
        redirectAfterPlaceOrder: false,
        defaults: {
            template: 'Pronko_LiqPayRedirect/payment/redirect-form',
            code: 'pronko_liqpay'
        },

        /**
         * @returns {String}
         */
        getCode: function () {
            return this.code;
        },

        /**
         * @returns {Boolean}
         */
        isActive: function () {
            return this.getCode() === this.isChecked();
        },

        /**
         * @param {String} field
         * @returns {String}
         */
        getSelector: function (field) {
            return '#' + this.getCode() + '_' + field;
        },

        afterPlaceOrder: function () {
            $.post(url.build('liqpay/checkout/form'), {
                'random_string': this._generateRandomString(30)
            }).done(function(data) {
                if (!data.status) {
                    return
                }
                if (data.status === 'success') {
                    if (data.content) {
                        var html = '<div id="liqPaySubmitFrom" style="display: none;">' + data.content + '</div>';
                        $('body').append(html);
                        // window.open('about:blank','POPUPW','width=900,height=700,scrollbars=yes,resizable=yes');
                        $('#liqPaySubmitFrom form:first').submit();
                    }
                } else {
                    if (data.redirect) {
                        window.location = data.redirect;
                    }
                }
            });
        },
        _generateRandomString: function(length) {
            if (!length) {
                length = 10;
            }
            var text = '';
            var possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            for (var i = 0; i < length; ++i) {
                text += possible.charAt(Math.floor(Math.random() * possible.length));
            }
            return text;
        }
    })
});
