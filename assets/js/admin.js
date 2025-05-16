jQuery(document).ready(function($) {
    if (typeof wpavatar === 'undefined') {
        console.error('WPAvatar admin script error: wpavatar object not defined');
        return;
    }

    if (typeof wpavatar_l10n === 'undefined') {
        console.error('WPAvatar admin script error: wpavatar_l10n object not defined');
        return;
    }

    $('.wpavatar-tab').on('click', function() {
        var tab = $(this).data('tab');
        if (!tab) return;

        $('.wpavatar-tab').removeClass('active');
        $(this).addClass('active');

        $('.wpavatar-section').hide();
        $('#wpavatar-section-' + tab).show();

        if (tab === 'cache' && $('#cache-stats').is(':empty')) {
            setTimeout(function() {
                $('#check-cache').trigger('click');
            }, 300);
        }

        if (window.history && window.history.pushState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
    });

    function updateCdnOptions() {
        var selectedType = $('input[name="wpavatar_cdn_type"]:checked').val();

        $('.cdn-option').hide();

        if (selectedType === 'cravatar_route') {
            $('.cravatar-route-option').show();
            forceMd5HashMethod(true);
        } else if (selectedType === 'third_party') {
            $('.third-party-option').show();
            checkIfCravatarRelated($('select[name="wpavatar_third_party_mirror"]').val());
        } else if (selectedType === 'custom') {
            $('.custom-cdn-option').show();
            checkIfCravatarRelated($('input[name="wpavatar_custom_cdn"]').val());
        }
    }

    function checkIfCravatarRelated(value) {
        if (value && value.toLowerCase().indexOf('cravatar') !== -1) {
            forceMd5HashMethod(true);
        } else {
            // 非Cravatar服务默认选择SHA256，但不强制
            var currentHashMethod = $('input[name="wpavatar_hash_method"]:checked').val();
            if (!currentHashMethod) {
                $('input[name="wpavatar_hash_method"][value="sha256"]').prop('checked', true);
            }
            forceMd5HashMethod(false);
        }
    }

    function forceMd5HashMethod(force) {
        if (force) {
            $('input[name="wpavatar_hash_method"][value="md5"]').prop('checked', true);
            $('input[name="wpavatar_hash_method"][value="sha256"]').prop('disabled', true);
            $('.hash-method-notice').show();
        } else {
            $('input[name="wpavatar_hash_method"][value="sha256"]').prop('disabled', false);
            $('.hash-method-notice').hide();
        }
    }

    $('input[name="wpavatar_cdn_type"]').on('change', updateCdnOptions);

    updateCdnOptions();

    $('select[name="wpavatar_third_party_mirror"]').on('change', function() {
        checkIfCravatarRelated($(this).val());
    });

    $('input[name="wpavatar_custom_cdn"]').on('input', function() {
        checkIfCravatarRelated($(this).val());
    });

    $('#check-cache').on('click', function() {
        var $button = $(this);
        var $stats = $('#cache-stats');

        $button.prop('disabled', true).text(wpavatar_l10n.checking);
        $stats.html('<p>' + wpavatar_l10n.checking_status + '</p>');

        $.ajax({
            type: 'POST',
            url: wpavatar.ajaxurl,
            data: {
                action: 'wpavatar_check_cache',
                nonce: wpavatar.nonce
            },
            success: function(response) {
                if (response.success) {
                    $stats.html(response.data);
                } else {
                    $stats.html('<div class="error"><p>' + (response.data || wpavatar_l10n.check_failed) + '</p></div>');
                }
            },
            error: function() {
                $stats.html('<div class="error"><p>' + wpavatar_l10n.request_failed + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text(wpavatar_l10n.check_cache);
            }
        });
    });

    $('#purge-cache').on('click', function() {
        var $button = $(this);
        var $stats = $('#cache-stats');
        var $status = $('#wpavatar-status');

        if (!confirm(wpavatar_l10n.confirm_purge)) {
            return;
        }

        $button.prop('disabled', true).text(wpavatar_l10n.purging);
        $stats.html('<p>' + wpavatar_l10n.purging_cache + '</p>');

        $.ajax({
            type: 'POST',
            url: wpavatar.ajaxurl,
            data: {
                action: 'wpavatar_purge_cache',
                nonce: wpavatar.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('notice-error')
                           .addClass('notice-success')
                           .text(response.data)
                           .show()
                           .delay(3000)
                           .fadeOut();

                    setTimeout(function() {
                        $('#check-cache').trigger('click');
                    }, 1000);
                } else {
                    $status.removeClass('notice-success')
                           .addClass('notice-error')
                           .text(response.data || wpavatar_l10n.purge_failed)
                           .show();
                }
            },
            error: function() {
                $status.removeClass('notice-success')
                       .addClass('notice-error')
                       .text(wpavatar_l10n.request_failed)
                       .show();
            },
            complete: function() {
                $button.prop('disabled', false).text(wpavatar_l10n.purge_cache);
            }
        });
    });

    $('#wpavatar-basic-form, #wpavatar-cache-form, #wpavatar-advanced-form, #wpavatar-shortcodes-form').on('submit', function(e) {
        var formId = $(this).attr('id');
        var $status = $('#wpavatar-status');
        var isValid = true;

        if (formId === 'wpavatar-basic-form' && $('input[name="wpavatar_cdn_type"]:checked').val() === 'custom') {
            var customCdn = $('input[name="wpavatar_custom_cdn"]').val().trim();
            if (!customCdn) {
                $status.removeClass('notice-success')
                       .addClass('notice-error')
                       .text(wpavatar_l10n.enter_custom_cdn)
                       .show();
                $('input[name="wpavatar_custom_cdn"]').focus();
                isValid = false;
            }
        }

        if (formId === 'wpavatar-cache-form' && $('input[name="wpavatar_enable_cache"]:checked').length > 0) {
            var cachePath = $('input[name="wpavatar_cache_path"]').val().trim();
            if (!cachePath) {
                $status.removeClass('notice-success')
                       .addClass('notice-error')
                       .text(wpavatar_l10n.enter_cache_path)
                       .show();
                $('input[name="wpavatar_cache_path"]').focus();
                isValid = false;
            }
        }

        if (!isValid) {
            e.preventDefault();
            return false;
        }

        return true;
    });

    if (window.location.search.indexOf('settings-updated=true') > -1) {
        $('#wpavatar-status')
            .removeClass('notice-error')
            .addClass('notice-success')
            .text(wpavatar_l10n.settings_saved)
            .show()
            .delay(3000)
            .fadeOut();
    }

    var currentTab = '';

    if (window.location.search.indexOf('tab=') > -1) {
        var urlParams = new URLSearchParams(window.location.search);
        currentTab = urlParams.get('tab');
    }

    if (!currentTab) {
        var $activeTab = $('.wpavatar-tab.active');
        if ($activeTab.length) {
            currentTab = $activeTab.data('tab');
        }
    }

    if (currentTab) {
        $('.wpavatar-tab[data-tab="' + currentTab + '"]').trigger('click');
    } else {
        $('.wpavatar-tab:first').trigger('click');
    }
});
