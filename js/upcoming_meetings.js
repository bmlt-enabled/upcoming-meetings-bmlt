/**
 * Upcoming Meetings BMLT - area filter.
 *
 * When an area is selected, re-query the meetings scoped to that area via admin-ajax
 * and swap the results markup in place.
 */
(function () {
    'use strict';

    function bindSelect(select) {
        if (select.dataset.umBound) {
            return;
        }
        select.dataset.umBound = '1';

        select.addEventListener('change', function () {
            var widget = select.closest('.upcoming-meetings-widget');
            if (!widget) {
                return;
            }
            var results = widget.querySelector('.upcoming-meetings-results');
            if (!results) {
                return;
            }

            var args;
            try {
                args = JSON.parse(results.getAttribute('data-um-args') || '{}');
            } catch (e) {
                args = {};
            }
            args.action = 'upcoming_meetings_filter';
            args.nonce = window.UpcomingMeetingsFilter ? window.UpcomingMeetingsFilter.nonce : '';
            args.service_body = select.value;

            var body = Object.keys(args).map(function (key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(args[key]);
            }).join('&');

            results.style.opacity = '0.5';
            select.disabled = true;

            fetch(window.UpcomingMeetingsFilter.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                results.innerHTML = html;
            }).catch(function () {
                // Leave the existing results in place on failure.
            }).then(function () {
                results.style.opacity = '1';
                select.disabled = false;
            });
        });
    }

    function init() {
        var selects = document.querySelectorAll('.upcoming-meetings-area-filter select');
        for (var i = 0; i < selects.length; i++) {
            bindSelect(selects[i]);
        }
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
