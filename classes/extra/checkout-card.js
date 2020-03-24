jQuery(document).ready(function($){
    $('#PurePay_payment_form').card({
        container: '.card-area',
        width: 310,
        formSelectors: {
            nameInput: 'input[name="full-name"]',
            numberInput: 'input[name="cardNumber"]',
            cvcInput: 'input[name="cardCVV"]',
            expiryInput: 'input[name="cardExpiryMonth"], input[name="cardExpiryYear"]'
        }
    });
    $( ".card-input" ).keyup(function() {
        console.log('chaning');
        if ($(".jp-card").hasClass("jp-card-identified")) {
            $('.pure-pay-logo').fadeOut('fast');
        }
        if ($(".jp-card").hasClass("jp-card-unknown")) {
            $('.pure-pay-logo').fadeIn('fast');
        }
    });
});

