/**
 * @file
 * Footer live search: dropdown suggestions, keyboard nav, Enter → results page.
 *
 * Behavior:
 * - input → debounce 180ms → GET suggestUrl?q=… (JSON {results: [{type,title,description,path}]})
 * - dropdown grouped by type: service / doctor / page / info, with <mark> highlight
 * - ArrowDown/ArrowUp navigate, Enter opens active suggestion or /searching?q=…
 * - Escape closes, click outside closes, Ctrl/⌘+K focuses the field
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.customSearch = {
    attach: function (context) {
      once('customSearch', '[data-custom-search]', context).forEach(function (root) {
        var input = root.querySelector('[data-custom-search-input]');
        var box = root.querySelector('[data-custom-search-results]');
        var kbd = root.querySelector('[data-custom-search-kbd]');
        if (!input || !box) {
          return;
        }

        var suggestUrl = input.getAttribute('data-suggest-url') ||
          (window.drupalSettings && window.drupalSettings.customSearch && window.drupalSettings.customSearch.suggestUrl) ||
          '/searching/suggest';
        var resultsUrl = input.getAttribute('data-results-url') ||
          (window.drupalSettings && window.drupalSettings.customSearch && window.drupalSettings.customSearch.resultsUrl) ||
          '/searching';
        var minLength = parseInt(input.getAttribute('data-min-length') || '2', 10) || 2;

        var isMac = navigator.platform && navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        if (kbd) {
          kbd.textContent = isMac ? '⌘' : 'Ctrl';
        }

        var CAT_LABELS = { service: 'Services', doctor: 'Doctors', page: 'Pages', info: 'Info' };
        try {
          var lang = document.documentElement.lang || 'en';
          if (lang.indexOf('ru') === 0) {
            CAT_LABELS = { service: 'Услуги', doctor: 'Врачи', page: 'Разделы', info: 'Инфо' };
          }
        } catch (e) { /* ignore */ }

        var activeIdx = -1;
        var debounceTimer = null;
        var abortController = null;

        function norm(s) {
          return (s || '').toLowerCase().replace(/[^a-zа-яё0-9]+/gi, ' ').trim();
        }
        function esc(s) {
          return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function hi(text, q) {
          if (!q) {
            return esc(text);
          }
          var n = norm(text);
          var idx = n.indexOf(q);
          if (idx < 0) {
            return esc(text);
          }
          var before = text.substring(0, idx);
          var match = text.substring(idx, idx + q.length);
          var after = text.substring(idx + q.length);
          return esc(before) + '<mark>' + esc(match) + '</mark>' + esc(after);
        }

        function hide() {
          box.classList.remove('show');
          box.innerHTML = '';
          activeIdx = -1;
        }

        function setActive(n) {
          var links = box.querySelectorAll('a');
          if (!links.length) {
            return;
          }
          links.forEach(function (a) { a.classList.remove('act'); });
          if (n >= 0 && n < links.length) {
            links[n].classList.add('act');
            links[n].scrollIntoView({ block: 'nearest' });
          }
          activeIdx = n;
        }

        function renderResults(out, qRaw) {
          var q = norm(qRaw);
          activeIdx = -1;
          if (!out.length) {
            box.innerHTML = '<div class="cs-empty">Nothing found for &lt;' + esc(qRaw) + '&gt;</div>';
            box.classList.add('show');
            return;
          }
          var cats = { service: [], doctor: [], page: [], info: [] };
          out.forEach(function (r) {
            if (cats[r.type]) {
              cats[r.type].push(r);
            } else {
              cats.page.push(r);
            }
          });
          var html = '';
          ['service', 'doctor', 'page', 'info'].forEach(function (c) {
            if (!cats[c].length) {
              return;
            }
            html += '<div class="cs-cat">' + esc(CAT_LABELS[c]) + '</div>';
            cats[c].forEach(function (r) {
              var globalIdx = out.indexOf(r);
              var url = r.path || '#';
              html += '<a href="' + esc(url) + '" data-idx="' + globalIdx + '">' +
                '<span class="cs-body"><span class="cs-title">' + hi(r.title, q) + '</span>' +
                '<span class="cs-desc">' + hi(r.description || '', q) + '</span></span>' +
                '<span class="cs-arrow">›</span></a>';
            });
          });
          html += '<div class="cs-foot"><span>↑↓ navigate</span><span>Enter → all results</span><span>Esc close</span></div>';
          box.innerHTML = html;
          box.classList.add('show');
          box.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
              hide();
            });
          });
        }

        function build(qRaw) {
          var q = norm(qRaw);
          if (!q || q.length < minLength) {
            hide();
            return;
          }
          clearTimeout(debounceTimer);
          if (abortController) {
            try { abortController.abort(); } catch (e) { /* ignore */ }
          }
          box.innerHTML = '<div class="cs-empty">…</div>';
          box.classList.add('show');
          debounceTimer = setTimeout(function () {
            abortController = ('AbortController' in window) ? new AbortController() : null;
            var opts = { headers: { Accept: 'application/json' } };
            if (abortController) {
              opts.signal = abortController.signal;
            }
            fetch(suggestUrl + '?q=' + encodeURIComponent(qRaw), opts)
              .then(function (r) {
                if (!r.ok) {
                  throw new Error('HTTP ' + r.status);
                }
                return r.json();
              })
              .then(function (data) {
                renderResults((data && data.results) || [], qRaw);
              })
              .catch(function (err) {
                if (err && err.name === 'AbortError') {
                  return;
                }
                hide();
              });
          }, 180);
        }

        input.addEventListener('input', function () { build(this.value); });
        input.addEventListener('focus', function () {
          if (this.value.trim()) {
            build(this.value);
          }
        });
        input.addEventListener('keydown', function (e) {
          var links = box.querySelectorAll('a');
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIdx + 1, links.length - 1));
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIdx - 1, 0));
          } else if (e.key === 'Enter') {
            e.preventDefault();
            var a = box.querySelector('a.act');
            if (a) {
              window.location.href = a.getAttribute('href');
            } else if (input.value.trim()) {
              window.location.href = resultsUrl + '?q=' + encodeURIComponent(input.value.trim());
            }
          } else if (e.key === 'Escape') {
            e.preventDefault();
            input.blur();
            hide();
          }
        });

        document.addEventListener('click', function (e) {
          if (!e.target.closest('[data-custom-search]')) {
            hide();
          }
        });
        document.addEventListener('keydown', function (e) {
          if ((e.metaKey || e.ctrlKey) && (e.key || '').toLowerCase() === 'k') {
            // Only steal the shortcut when our block is on the page.
            e.preventDefault();
            input.focus();
            input.select();
          }
        });
      });
    }
  };
})(Drupal, once);
