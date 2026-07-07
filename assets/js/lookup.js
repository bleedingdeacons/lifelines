/* global LifeLinesLookup */
(function () {
    'use strict';

    if (typeof LifeLinesLookup === 'undefined') {
        return;
    }

    var cfg = LifeLinesLookup;

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
                tr.appendChild(el('td', null, value === null || value === undefined ? '' : value));
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

        function run(term) {
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
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    setStatus(cfg.i18n.error);
                });
        }

        var onInput = debounce(function () {
            run(input.value.trim());
        }, 200);

        input.addEventListener('input', onInput);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var widgets = document.querySelectorAll('.lifelines-lookup');
        Array.prototype.forEach.call(widgets, initWidget);
    });
})();
