/**
 * Settings scripts for WooCommerce Lottery Plugin
 * Version: 2.2.2
 * Generated: July 22, 2025
 */
jQuery(document).ready(function($) {
    if (typeof wclp_admin === 'undefined') {
        return;
    }

    function initSettingsForm() {
        $(document).on('click', '#wclp-test-random-button', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const $form = $('#wclp-test-random-generator');
            const $result = $('#wclp-test-result');
            const $button = $(this);
            const totalNumbers = parseInt($form.find('#wclp-total-numbers').val());
            const winnersCount = parseInt($form.find('#wclp-winners-count').val());
            const exclusions = $form.find('#wclp-exclusions').val();

            if (isNaN(totalNumbers) || totalNumbers < 1 || isNaN(winnersCount) || winnersCount < 1 || winnersCount > totalNumbers) {
                $result.html('<div class="notice notice-error is-dismissible"><p>Неверное количество номеров или выигрышей</p></div>');
                return;
            }

            $button.prop('disabled', true).text('Тестирование...');
            $result.html('<div class="notice notice-info is-dismissible"><p>Загрузка...</p></div>');

            $.ajax({
                url: wclp_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wclp_test_random_generator',
                    nonce: wclp_admin.nonce,
                    total_numbers: totalNumbers,
                    winners_count: winnersCount,
                    exclusions: exclusions
                },
                success: function(response) {
                    if (response.success && response.data && response.data.numbers) {
                        $result.html(
                            '<div class="notice notice-success is-dismissible"><p>' +
                            'Сгенерированные номера: ' + response.data.numbers.join(', ') +
                            '<br>Генератор: ' + response.data.generator +
                            '</p></div>'
                        );
                    } else {
                        $result.html('<div class="notice notice-error is-dismissible"><p>Ошибка: ' + (response.data && response.data.message || 'Неизвестная ошибка') + '</p></div>');
                    }
                    $button.prop('disabled', false).text('Тестировать генератор');
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="notice notice-error is-dismissible"><p>Ошибка AJAX: ' + (xhr.responseText || error) + '</p></div>');
                    $button.prop('disabled', false).text('Тестировать генератор');
                }
            });
        });
    }

    // Инициализация формы
    initSettingsForm();

    // Повторная попытка инициализации через 2 секунды
    setTimeout(function() {
        initSettingsForm();
    }, 2000);
});