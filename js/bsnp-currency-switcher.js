jQuery(document).ready(function () {


    if (typeof list_of_currencies != 'undefined') {

        list = list_of_currencies;

    } else list = null;


    if (typeof Cookies.get('currency_code') === 'undefined') {

        Cookies.set('currency_code', 'Default currency');

    }

    if (typeof Cookies.get('is_locally_supported') === 'undefined') {

        if (typeof is_currency_locally_supported != 'undefined') {

            Cookies.set('is_locally_supported', is_currency_locally_supported);

        }

    }

    if (typeof Cookies.get('us_ex_rate') === 'undefined') {

        if (typeof local_to_us_ex_rate != 'undefined') {

            Cookies.set('us_ex_rate', ( local_to_us_ex_rate ));

        }

    }

    add_currency_drop_down_list();


    jQuery("#bsnp_ex_selector").on('change', function () {

        currency = jQuery(this).find(':selected').text();

        exRate = ( 1 / this.value );

        exFactor = (  local_to_us_ex_rate / exRate );

        Cookies.set('currency_code', currency);

        Cookies.set('ex_factor', exFactor);

        is_supported = "";

        if (typeof( list_of_currencies[currency] ) !== 'undefined') {

            is_supported = list_of_currencies[currency][1];

        }

        Cookies.set('is_shopper_selection_supported', is_supported);

        jQuery('.ex_rate').attr('value', exRate);

        jQuery('.bs_amout').remove();

        jQuery('.amount').hide();

        if ('Default currency' == currency) {

            jQuery('.amount').show();

            jQuery('.bs_amout').hide();

        } else {

            jQuery('.amount').each(function (idx, val) {
                    formattedPrice = parseFloat(( jQuery(this).text() ).replace(/[^\d.]/g, ''));
                    //newPrice = ( formattedPrice * exFactor ).toFixed(2) + " " + currency;
                    newPrice = php_round((formattedPrice * exFactor).toPrecision(6), 2);
                    priceForPrint = newPrice.toFixed(2).replace(/./g, function (c, i, a) {
                            return i && c !== "." && ((a.length - i) % 3 === 0) ? ',' + c : c;
                        }) + " " + currency;
                    jQuery(this).after("<span class='amonut bs_amout'>" + priceForPrint + "</span>");
                }
            );
        }
    });


    jQuery('body').on('updated_checkout', function () {

        if (typeof list_of_currencies === 'undefined') {

            return;

        }

        if (jQuery('body').hasClass('woocommerce-checkout')) {

            currencyCode = Cookies.get('currency_code');

            exFactor = Cookies.get('ex_factor');

            if ("N" == is_currency_locally_supported) {

                if ("Default currency" == currencyCode) {

                    get_price_in_us();

                } else {

                    get_price_in_shopper_currency(currencyCode, exFactor);

                }
            }

            if ("Y" == is_currency_locally_supported) {

                if (currencyCode != "Default currency") {

                    get_price_in_shopper_currency(currencyCode, exFactor);

                }

            }
        }
    });

    if (jQuery('body').hasClass('woocommerce-cart') || ( jQuery('body').hasClass('woocommerce-page') && !jQuery('body').hasClass('woocommerce-account') )) {

        currencyCode = Cookies.get('currency_code');

        exFactor = Cookies.get('ex_factor');

        if (currencyCode != "Default currency") {

            get_price_in_shopper_currency(currencyCode, exFactor);

        }

    }

    jQuery(document).ajaxComplete(function (event, xhr, settings) {
        var reloads = 0;

        if (jQuery('body').hasClass('woocommerce-cart')) {
            //location.reload();
        }
    });
});


function get_price_in_us() {
    jQuery('.amount').each(function (idx, val) {
            formattedPrice = parseFloat(( jQuery(this).text() ).replace(/[^\d.]/g, ''));
            //newPrice = ( formattedPrice * local_to_us_ex_rate ).toFixed(2) + " USD";
            newPrice = php_round(( formattedPrice * local_to_us_ex_rate ).toPrecision(6), 2);
            priceForPrint = newPrice.toFixed(2).replace(/./g, function (c, i, a) {
                    return i && c !== "." && ((a.length - i) % 3 === 0) ? ',' + c : c;
                }) + " USD";
            jQuery(this).after("<span class='amonut bs_amout'>" + " | " + newPrice + "</span> ");
        }
    );
}


function get_price_in_shopper_currency(currency, exFactor) {
    if (null == list) {
        return;
    }
    shopper_currency_is_localy_supported = list[Cookies.get('currency_code')][1];
    jQuery('.amount').each(function (idx, val) {
        // console.log('before: '); console.log( jQuery(this).next());
        formattedPrice = parseFloat(( jQuery(this).text() ).replace(/[^\d\.]/g, ''));
        //newPrice = ( formattedPrice * exFactor ).toFixed(2);
        newPrice = php_round(( formattedPrice * exFactor ).toPrecision(6), 2);
        priceForPrint = newPrice.toFixed(2).replace(/./g, function (c, i, a) {
            return i && c !== "." && ((a.length - i) % 3 === 0) ? ',' + c : c;
        });
        //price_in_us = ( formattedPrice * local_to_us_ex_rate ).toFixed(2);
        price_in_us = php_round(( formattedPrice * local_to_us_ex_rate ).toPrecision(6), 2);
        price_in_us_string = ("N" == shopper_currency_is_localy_supported ) ? " | " + price_in_us + " USD" : "";
        if ("Y" == shopper_currency_is_localy_supported) {
            jQuery('#bsnp_cc_price_override').attr('value', newPrice);
            jQuery('#bsnp_cc_currency').attr('value', currency);
            jQuery('#bsnp_bn2_price_override').attr('value', newPrice);
            jQuery('#bsnp_bn2_currency').attr('value', currency);
        } else {
            jQuery('#bsnp_cc_price_override').attr('value', price_in_us);
            jQuery('#bsnp_bn2_price_override').attr('value', price_in_us);
            jQuery('#bsnp_bn2_currency').attr('value', 'USD');
            jQuery('#bsnp_cc_currency').attr('value', 'USD');
        }
        //  if(jQuery('.bs_amout').length > 1) console.log('idan');
        jQuery(this).after("<span class='amonut bs_amout'>" + priceForPrint + " " + currency + ( jQuery('body').hasClass('woocommerce-cart') ? "" : price_in_us_string ) + "</span> ");

        //console.log('after: '); console.log( jQuery(this).next());
    });
    jQuery('.amount').hide();
}


function add_currency_drop_down_list() {

    if (null == list) {

        return;

    }

    if (typeof Cookies.get('currency_code') != 'undefined') {

        $shopperSelection = Cookies.get('currency_code');

    }

    currenciesList = '<option value="1">Default currency</option>';

    jQuery.each(list, function (i, data) {

        if (i == $shopperSelection) {

            $selected = "selected=selected";

        } else {

            $selected = '';

        }

        currenciesList += '<option value="' + data[0] + '"' + $selected + '>' + i + '</option>';

    });

    jQuery(".orderby").after("<select id='bsnp_ex_selector'> </select> ");

    jQuery("#bsnp_ex_selector").append(currenciesList);

}


// This function will round numbers in the same way php does
function php_round(value, precision, mode) {

    var m, f, isHalf, sgn;

    precision |= 0;

    m = Math.pow(10, precision);

    value *= m;

    sgn = (value > 0) | -(value < 0);

    isHalf = value % 1 === 0.5 * sgn;

    f = Math.floor(value);

    if (isHalf) {
        switch (mode) {
            case 'PHP_ROUND_HALF_DOWN':
                value = f + (sgn < 0);
                break;
            case 'PHP_ROUND_HALF_EVEN':
                value = f + (f % 2 * sgn);
                break;
            case 'PHP_ROUND_HALF_ODD':
                value = f + !(f % 2);
                break;
            default:
                value = f + (sgn > 0);
        }
    }

    return (isHalf ? value : Math.round(value)) / m;
}

