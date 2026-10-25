(function ($) {
    'use strict';

    var cfg    = window.bqucssAdmin || {};
    var ajax   = cfg.ajaxUrl || '';
    var nonce  = cfg.nonce || '';
    var i18n   = cfg.i18n || {};

    // Clear cache button
    $('#bqucss-clear-cache').on('click', function (e) {
        e.preventDefault();

        var $btn    = $(this);
        var $status = $('#bqucss-clear-status');

        $btn.prop('disabled', true);
        $status.removeClass('error').text(i18n.clearing || 'Clearing…');

        $.post(ajax, {
            action: 'bqucss_clear_cache',
            nonce: nonce
        })
        .done(function (res) {
            if (res && res.success) {
                $status.text(i18n.cleared || 'Cache cleared!');
                if (res.data && typeof res.data.count !== 'undefined') {
                    $('#bqucss-cache-count').text(res.data.count);
                }
            } else {
                $status.addClass('error').text(i18n.error || 'Error — please try again.');
            }
        })
        .fail(function () {
            $status.addClass('error').text(i18n.error || 'Error — please try again.');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

}(jQuery));
