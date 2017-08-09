/**
 * Created by yarivkohn on 3/25/15.
 */

//jQuery('#loadingDiv').hide();

(function (bsnpCheckoutCse) {
    // The global jQuery object is passed as a parameter
    bsnpCheckoutCse(jQuery, BlueSnap);
}(function ($, BlueSnap) {
    $.noConflict();

    if ($('form#order_review').length > 0) {
        //  $('#order_review').attr( 'class', 'checkout' );
        var $checkoutForm = $('#order_review');
        BlueSnap.setTargetFormId('order_review');
    }
    else {
        var $checkoutForm = $('form.checkout');
        $('form.checkout').attr('id', 'checkout-form');
        BlueSnap.setTargetFormId('checkout-form');
    }


    $checkoutForm.submit(function () {

        if ($('form#order_review').length > 0) {

            jQuery('#place_order').attr('disabled', 'disabled');

        }

        var number = $('.wc-credit-card-form-card-number').val();
        var cvv = $('.wc-credit-card-form-card-cvc').val();
        card_type = convertCardTypeToBSNPType($.payment.cardType(number));
        var last_digit = number.slice(-4);
        cc_num_of_digits = number.replace(/\s/g, '').length;
        cvv_num_of_digits = cvv.replace(/\s/g, '').length;

        $('.credit-card-name-field').attr('value', card_type);
        $('.credit-card-last-digit').attr('value', last_digit);
        $('.credit-card-digit-num').attr('value', cc_num_of_digits);
        $('.cvv-digit-num').attr('value', cvv_num_of_digits);

    });


    $('body').on('click', '#payment_method_wc_gateway_bluesnap_cc', function () {
        var is_return_shopper = $('.is_return_shopper').val();

        if (is_return_shopper == "true") {
            $('.bsnp_checkout_table').css('display', 'none');

        }
    });

    $('body').on('click', '.bsnp_new_card', function () {
        var $form = $(this).parents('form');

        $form.find('.bsnp_checkout_table').show();
        $form.find('input[name="reused_credit_card"]').each(function () {
            $(this).removeAttr('checked');
        });
    });

    $('body').on('click', 'input[name="reused_credit_card"]', function () {
        var $form = $(this).parents('form');

        $form.find('.bsnp_checkout_table').hide();
    });


    function convertCardTypeToBSNPType(cardType) {

        if ('dinersclub' == cardType) {

            return 'diners';

        } else {

            return cardType;

        }

    }


    function ccMinNumOfDigits(card_type) {
        $ccMinNumOfDigits = 16;
        switch (card_type) {
            case 'amex':
                $ccMinNumOfDigits = 15;
                break;
            case 'dinersclub':
            case 'diners':
                $ccMinNumOfDigits = 14;
                break;
            case 'visa':
                $ccMinNumOfDigits = 13;
                break;
            default:
                $ccMinNumOfDigits = 16;
        }
        return $ccMinNumOfDigits;
    }

    $checkoutForm.on('submit', function () {

        var $existingCard = $(this).find('input[name="reused_credit_card"]:checked'),
            $ccNumber = $(this).find('input#creditCard'),
            $expMonth = $(this).find('select#wc_gateway_bluesnap_cc_exp_month'),
            $expYear = $(this).find('select#wc_gateway_bluesnap_cc_exp_year'),
            $cvv = $(this).find('input#cvv'),
            $ccMinNumOfDigits = ccMinNumOfDigits(card_type);
        hasError = false;

        if (!$ccNumber.val() || ( ( cc_num_of_digits < $ccMinNumOfDigits ) )) {
            $ccNumber.addClass('error');
            hasError = true;
        } else {
            $ccNumber.removeClass('error');
            $ccNumber.addClass('woocommerce-validated');
        }

        if (!$expMonth.val()) {
            $expMonth.addClass('error');
            hasError = true;
        } else {
            $expMonth.removeClass('error');
            $expMonth.addClass('woocommerce-validated');
            hasError = false;
        }

        if (!$expYear.val()) {
            $expYear.addClass('error');
            hasError = true;
        } else {
            $expYear.removeClass('error');
            $expYear.addClass('woocommerce-validated');
            hasError = false;
        }

        if (!$cvv.val() || ( ( 'amex' != card_type ) && ( cvv_num_of_digits != 3 ) )) {
            $cvv.addClass('error');
            hasError = true;
        } else {
            $cvv.removeClass('error');
            hasError = false;
        }

        if (( 'amex' == card_type ) && ( cvv_num_of_digits != 4 )) {
            $cvv.addClass('error');
            hasError = true;
        } else {
            $cvv.removeClass('error');
            $cvv.addClass('woocommerce-validated');
            hasError = false;
        }

        if (hasError) {
            return false;
        }
    });

    $(document).ready(function(){
       $('body').on('click','#bsnp_cvv_hint_mobile img',function(){
          $('.pli-cvv-wrapper').show();
       });
        $('body').on('click','.mobile_close_btn',function(){
            $('.pli-cvv-wrapper').hide();
        });
    });
}));