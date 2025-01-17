(function ($) {

    $('#xero-send-invoice, #xero-send-invoice-payment, #xero-resend-invoice').on(
        'click',
        function (e) {
            e.preventDefault();

            var xero_invoice = $(this).data('id');
            var xero_type = $(this).data('call');

            if (null !== xero_invoice && null !== xero_type) {
                xero_send_invoice(xero_invoice, xero_type);
            }
        }
    );

    if ($('input[name="use_extra_sales_account"]').length) {
        var checked_value = $('input[name="use_extra_sales_account"]:checked').val();
        $('.' + checked_value).toggle();
    }

    $('input[name="use_extra_sales_account"]').on(
        'change',
        function () {
            $('.xeroom_use_extra_sales_accounts').children().hide();
            var to_display = $('.' + $(this).val());

            $(to_display).toggle();
        }
    );

    if ($('input[name="xeroom_tax_method"]').length) {
        var checked_value = $('input[name="xeroom_tax_method"]:checked').val();
        $('.xero_tax_methods_content').find('.xero_inputdiv').hide();

        if ('xero_complex_tax' == checked_value) {
            $('.xero_tax_methods_content').find('.xero_label').css({'text-align': 'left', 'width': '100%'});
        }

        $('.' + checked_value).show();
    }

    $('input[name="xeroom_tax_method"]').on(
        'change',
        function () {
            $('.xero_tax_methods_content').find('.xero_inputdiv').hide();
            var to_display = $('.' + $(this).val());

            if ('xero_complex_tax' == $(this).val()) {
                $('.xero_tax_methods_content').find('.xero_label').css({'text-align': 'left', 'width': '100%'});
            } else {
                $('.xero_tax_methods_content').find('.xero_label').removeAttr('style');
            }

            $(to_display).toggle();
        }
    );

    if ($('.xeroom-select').length > 0) {
        $('.xeroom-select').select2();
    }

    $('form #xeroom_black_list').on(
        'click',
        function (e) {
            e.preventDefault();
            $('#xeroom_sync_response').slideUp().html('');
            
            var black_list = jQuery('textarea[name="xero_blacklisted_skus"]').val();

            jQuery.ajax(
                {
                    type: 'POST',
                    url: xero_ajax_object.xeroajax,
                    data: {
                        black_list: black_list,
                        nonce: xero_ajax_object.nonce,
                        action: 'xero_black_list'
                    },
                    beforeSend: function () {
                        $('#xeroom_sync_response').html(xero_ajax_object.xero_loading).slideDown();
                    },
                    success: function (data) {
                        $('#xeroom_sync_response').html(data).slideDown();
                    }
                }
            );
        }
    );

    $('form #xeroom_sync_now').on(
        'click',
        function (e) {
            e.preventDefault();
            $('#xeroom_sync_response').slideUp().html('');

            var sync_master,
                sync_schedule,
                batch_sync_size,
                debug_mode = 0;

            sync_master = jQuery('input[name="sync_master"]:checked').val();
            sync_schedule = jQuery('input[name="sync_schedule"]:checked').val();
            batch_sync_size = jQuery('input[name="batch_sync_size"]').val();
            if(jQuery('input[name="synch_debug_mode"]').is(':checked')) {
                debug_mode = 1;
            }

            jQuery.ajax(
                {
                    type: 'POST',
                    url: xero_ajax_object.xeroajax,
                    data: {
                        master: sync_master,
                        schedule: sync_schedule,
                        size: batch_sync_size,
                        synch_debug_mode: debug_mode,
                        nonce: xero_ajax_object.nonce,
                        action: 'xero_sync_stock'
                    },
                    beforeSend: function () {
                        $('#xeroom_sync_response').html(xero_ajax_object.xero_loading).slideDown();
                    },
                    success: function (data) {
                        $('#xeroom_sync_response').html(data).slideDown();
                        if (typeof fetchXeroomSyncStatus === "function") {
                            setInterval(fetchXeroomSyncStatus, 60000); // Update every minute
                        }
                    }
                }
            );
        }
    );

    $('form #xeroom_prod_sync_now').on(
        'click',
        function (e) {
            e.preventDefault();
            $('#xeroom_sync_response').slideUp().html('');

            var sync_master,
                what_to_update,
                sync_schedule,
                batch_sync_size;

            sync_master = jQuery('input[name="sync_prod_master"]:checked').val();
            sync_schedule = jQuery('input[name="sync_prod_schedule"]:checked').val();
            what_to_update = jQuery('input[name="what_to_update"]:checked').val();
            batch_sync_size = jQuery('input[name="batch_product_sync_size"]').val();

            jQuery.ajax(
                {
                    type: 'POST',
                    url: xero_ajax_object.xeroajax,
                    data: {
                        master: sync_master,
                        schedule: sync_schedule,
                        what_to_update: what_to_update,
                        size: batch_sync_size,
                        nonce: xero_ajax_object.nonce,
                        action: 'xero_sync_products'
                    },
                    beforeSend: function () {
                        $('#xeroom_sync_response').html(xero_ajax_object.xero_loading).slideDown();
                    },
                    success: function (data) {
                        $('#xeroom_sync_response').html(data).slideDown();
                        if (typeof fetchXeroomProductSyncStatus === "function") {
                            setInterval(fetchXeroomProductSyncStatus, 60000); // Update every minute
                        }
                    }
                }
            );
        }
    );

    $('body').on(
        'click',
        '#xeroo-cancel-jobs',
        function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to cancel all the bulk jobs?')) {
                jQuery.ajax(
                    {
                        type: 'POST',
                        url: xero_ajax_object.xeroajax,
                        data: {
                            nonce: xero_ajax_object.nonce,
                            action: 'xero_cancel_jobs'
                        },
                        success: function (data) {
                            location.reload();
                        }
                    }
                );
            }
        }
    );

    if ('woocommerce_page_wc-orders' == xero_ajax_object.xero_orders && '1' === xero_ajax_object.xero_bulk_status) {
        setTimeout(xeroom_display_invoice_info, 10000);
    }

    var xeroom_export_label = $('#xeroom-export-all');
    var xeroom_export_type = $('input[name="xeroom_export_type"]').val();

    if('downloadExcel' == xeroom_export_type) {
        xeroom_export_label.html('Download');
    }

    if('sendToXero' == xeroom_export_type) {
        xeroom_export_label.html('Run Job');
    }

    $('input[name="xeroom_export_type"]').change(function(){
        console.log($(this).val());
        if('downloadExcel' == $(this).val()) {
            xeroom_export_label.html('Download');
        }

        if('sendToXero' == $(this).val()) {
            xeroom_export_label.html('Run Job');
        }
    });
})(jQuery);

function xeroom_display_invoice_info() {
    jQuery.ajax(
        {
            type: 'POST',
            url: xero_ajax_object.xeroajax,
            data: {
                nonce: xero_ajax_object.nonce,
                action: 'xero_sync_bulk_info'
            },
            success: function (response) {
                if (response.trim() !== '') {
                    if (jQuery('.xero_bulk_message').length) {
                        jQuery('.xero_bulk_message').remove();
                    }
                    if (jQuery('.xero-bulk-info').length) {
                        jQuery('.xero-bulk-info').html('<p>' + response + '</p>');
                    } else {
                        jQuery('<div class="updated notice notice-success is-dismissible xero-bulk-info"><p>' + response + '</p></div>').insertAfter('.wp-header-end');
                    }
                } else {
                    jQuery('.xero-bulk-info').remove();
                    window.location.reload();
                }
            },
            complete: function (response) {
                setTimeout(xeroom_display_invoice_info, 10000);
            }
        }
    );
}

function xero_send_invoice(invoice, call_type) {
    jQuery.ajax(
        {
            type: 'POST',
            url: xero_ajax_object.xeroajax,
            data: {
                invoice: invoice,
                call_type: call_type,
                nonce: xero_ajax_object.nonce,
                action: 'xero_send_invoice'
            },
            success: function (data) {
                if(confirm(data)){
                    console.log('Confirmed!');
                    window.location.reload();
                }
            }
        }
    );
}
