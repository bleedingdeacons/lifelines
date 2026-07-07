/* global LifeLinesLookup */
(function () {
    'use strict';

    if (typeof LifeLinesLookup === 'undefined') {
        return;
    }

    var cfg = LifeLinesLookup;

    // Per-page key so different lookup pages remember their own state.
    var STORE_KEY = 'lifelinesLookup:' + window.location.pathname;

    // Take control of scroll restoration so we can re-apply it after the
    // (asynchronously loaded) results have rendered and the page has its height.
    if ('scrollRestoration' in window.history) {
        try { window.history.scrollRestoration = 'manual'; } catch (e) { /* noop */ }
    }

    function saveState(state) {
        try {
            window.sessionStorage.setItem(STORE_KEY, JSON.stringify(state));
        } catch (e) { /* storage unavailable — degrade silently */ }
    }

    function loadState() {
        try {
            return JSON.parse(window.sessionStorage.getItem(STORE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    function urlQuery() {
        try {
            return new URL(window.location.href).searchParams.get('q');
        } catch (e) {
            return null;
        }
    }

    function reflectQueryInUrl(term) {
        try {
            var u = new URL(window.location.href);
            if (term) {
                u.searchParams.set('q', term);
            } else {
                u.searchParams.delete('q');
            }
            window.history.replaceState(null, '', u.toString());
        } catch (e) { /* noop */ }
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    function renderResults(container, columns, rows) {
        container.innerHTML = '';

        if (!rows.length) {
            return;
        }

        var table = el('table', 'lifelines-lookup__table');

        var thead = el('thead');
        var headRow = el('tr');
        columns.forEach(function (col) {
            headRow.appendChild(el('th', null, col.label));
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = el('tbody');
        rows.forEach(function (row) {
            var tr = el('tr');
            columns.forEach(function (col) {
                var value = row[col.key];
                var td = el('td', null, value === null || value === undefined ? '' : value);
                // Used by the responsive card layout on narrow screens to label
                // each value with its column heading.
                td.setAttribute('data-label', col.label);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        container.appendChild(table);
    }

    function initWidget(widget) {
        var input = widget.querySelector('.lifelines-lookup__input');
        var status = widget.querySelector('[data-role="status"]');
        var results = widget.querySelector('[data-role="results"]');
        var minChars = parseInt(cfg.minChars, 10) || 1;
        var controller = null;

        function setStatus(text) {
            status.textContent = text || '';
        }

        function persist() {
            saveState({ q: input.value.trim(), scrollY: window.pageYOffset || 0 });
        }

        // opts.restoreScroll — pixels to scroll to once results have rendered.
        function run(term, opts) {
            opts = opts || {};

            if (controller && typeof controller.abort === 'function') {
                controller.abort();
            }

            if (term.length < minChars) {
                results.innerHTML = '';
                setStatus(term.length ? cfg.i18n.typeMore : '');
                return;
            }

            setStatus(cfg.i18n.searching);

            var url = cfg.ajaxUrl +
                '?action=' + encodeURIComponent(cfg.action) +
                '&q=' + encodeURIComponent(term);

            var fetchOpts = {};
            if (typeof AbortController !== 'undefined') {
                controller = new AbortController();
                fetchOpts.signal = controller.signal;
            }

            fetch(url, fetchOpts)
                .then(function (resp) {
                    return resp.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success) {
                        setStatus(cfg.i18n.error);
                        return;
                    }
                    var data = payload.data || {};
                    var columns = data.columns || cfg.columns || [];
                    var rows = data.rows || [];
                    renderResults(results, columns, rows);
                    setStatus(rows.length ? '' : cfg.i18n.noResults);

                    if (typeof opts.restoreScroll === 'number' && opts.restoreScroll > 0) {
                        window.scrollTo(0, opts.restoreScroll);
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    setStatus(cfg.i18n.error);
                });
        }

        var onInput = debounce(function () {
            var term = input.value.trim();
            reflectQueryInUrl(term);
            persist();
            run(term);
        }, 200);

        input.addEventListener('input', onInput);

        // Keep the remembered scroll position current as the user reads results,
        // and once more as they leave the page.
        window.addEventListener('scroll', debounce(persist, 150), { passive: true });
        window.addEventListener('pagehide', persist);

        // Restore on load: an explicit ?q= in the URL wins, otherwise the last
        // remembered term for this page. Then re-apply the saved scroll position
        // after the results render.
        var saved = loadState();
        var fromUrl = urlQuery();
        var initialTerm = (fromUrl !== null && fromUrl !== '') ? fromUrl : (saved.q || '');

        if (initialTerm) {
            input.value = initialTerm;
            run(initialTerm, { restoreScroll: saved.scrollY });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('.lifelines-lookup');
        Array.prototype.forEach.call(widgets, initWidget);
    });
})();
