(function($) {
    'use strict';

    function toggleSwitch($toggle) {
        var url = $toggle.data('toggle-url');
        $.get(url, function(res) {
            if (res && res.success) {
                var checkbox = $toggle.find('input[type="checkbox"]')[0];
                checkbox.checked = !checkbox.checked;
                $toggle.attr('aria-checked', checkbox.checked);
            }
        }, 'json').fail(function() {
            phpbb.alert('', $toggle.data('toggle-error'));
        });
    }

    $(document).on('click', '.toggle-switch[data-toggle-url]', function(e) {
        e.preventDefault();
        toggleSwitch($(this));
    });

    $(document).on('keydown', '.toggle-switch[data-toggle-url]', function(e) {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggleSwitch($(this));
        }
    });
})(jQuery);