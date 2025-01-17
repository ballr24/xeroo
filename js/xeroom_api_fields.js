function zeroom_check_xls_upload() {
    $xeroom_xls = $('#file').val();
    $xeroom_extension = $xeroom_xls.replace(/^.*\./, '');
    if ($xeroom_xls == "") {
        alert('Please select excel file to upload');
    } else if ($xeroom_extension != "xlsx") {
        alert('File format is not supported!');
    } else {
        document.zeroom_upload_excel.submit();
    }
}

function submitLicKey() {
    var defaultG = document.getElementById('xero_default_payment');

    if (typeof (defaultG) != 'undefined' && defaultG != null) {
        if (defaultG.value == '') {
            alert("Enter Xero Default Payment Gateway Account");
            return false;
        }
    }

    // var xero_api_key = "";
    // var xero_api_secret = "";
    // xero_api_key = document.getElementById('xero_api_key').value;
    // xero_api_secret = document.getElementById('xero_api_secret').value;
    // if (xero_api_key == "") {
    //     alert('Please enter xero api key!');
    // } else if (xero_api_secret == "") {
    //     alert('Please enter xero secret key!');
    // } else {
    document.xero_api_key_submit.submit();
    // }
}

function check_license_key() {
    var lic_key = "";
    lic_key = document.getElementById('zeroom_license_key').value;
    if (lic_key == "") {
        alert('Please enter zeroom license key!');
    } else {
        document.zeroom_activate_license.submit();
    }
}

function check_zeroom_email() {
    var email = "";
    email = document.getElementById('zeroom_license_email').value;
    if (email == "") {
        alert('Please enter email address!');
    } else if (!isEmail(email)) {
        alert('Please enter valid email address!');
    } else {
        document.zeroom_email.submit();
    }
}

function isEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}

function showEmailForm() {
    document.getElementById("xero_email").style.display = "block";
}

function hideXeroomMessage() {
    document.getElementById("payment_auto_message").style.display = "none";
}

function showXeroomMessage() {
    document.getElementById("payment_auto_message").style.display = "block";
}

function hideEmailForm() {
    document.getElementById('zeroom_license_email').value = "";
    document.getElementById("xero_email").style.display = "none";
}

jQuery(document).ready(
    function ($) {
        if ($('#xero_set_invoice_duedate').length) {
            if ('use_custom_due_date' == $('#xero_set_invoice_duedate').val()) {
                jQuery('#xero_due_date_custom_days').show();
            }
            if ('use_specific_month_day' == $('#xero_set_invoice_duedate').val()) {
                jQuery('#xero_due_date_month_day').show();
            }
        }

        var inv_meta_input = $('#invoice_meta_no');
        var invoice_prefix_input = $('#invoice_prefix');
        var invoice_start_no_input = $('#invoice_start_no');
        var invoice_ref_input = $('#invoice_reference_prefix');
        var reference_no = $('input[name="xero_use_custom_meta_inv"]');

        if (reference_no.is(':checked')) {
//            invoice_ref_input.prop('disabled', true);
        }

        reference_no.change(function () {
            if (reference_no.is(':checked')) {
//                invoice_ref_input.prop('disabled', true);
            } else {
                invoice_ref_input.prop('disabled', false);
            }
        });

        if( inv_meta_input.length > 0 && inv_meta_input.val().length ) {
//            invoice_prefix_input.prop('disabled', true);
//            invoice_start_no_input.prop('disabled', true);
        }

        if( ( invoice_prefix_input.length > 0 && invoice_prefix_input.val().length ) || ( invoice_start_no_input.length > 0 && invoice_start_no_input.val().length ) ) {
//            inv_meta_input.prop('disabled', true);
        }

        inv_meta_input.on('input', function () {
            if ($(this).val().length) {
//                invoice_prefix_input.prop('disabled', true);
//                invoice_start_no_input.prop('disabled', true);
            } else {
                invoice_prefix_input.prop('disabled', false);
                invoice_start_no_input.prop('disabled', false);
            }
        });

        invoice_prefix_input.on('input', function () {
            if ($(this).val().length) {
//                inv_meta_input.prop('disabled', true);
            } else {
                inv_meta_input.prop('disabled', false);
            }
        });
        invoice_start_no_input.on('input', function () {
            if ($(this).val().length) {
//                inv_meta_input.prop('disabled', true);
            } else {
                inv_meta_input.prop('disabled', false);
            }
        });
        
        var shippingPriceDescription = $('#shipping_price_description');
        var showShippingDetails = $('#xero_show_shipping_details');

        // Function to toggle the disabled state of the input field.
        function toggleShippingPriceDescription() {
            shippingPriceDescription.prop('disabled', showShippingDetails.is(':checked'));
        }

        // Initialize the state on page load.
        toggleShippingPriceDescription();

        // Add event listener to the checkbox.
        showShippingDetails.on('change', function () {
            toggleShippingPriceDescription();
        });
    }
);
jQuery(document.body).on(
    'change',
    '#xero_set_invoice_duedate',
    function () {
        if ('use_custom_due_date' == this.value) {
            jQuery('#xero_due_date_custom_days').show();
        } else {
            jQuery('#xero_due_date_custom_days').hide();
        }
        if ('use_specific_month_day' == this.value) {
            jQuery('#xero_due_date_month_day').show();
        } else {
            jQuery('#xero_due_date_month_day').hide();
        }
    }
);
