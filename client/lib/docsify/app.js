(function () {
    function readmeToApi(url) {
        try {
            var m = new URL(url, window.location.href).pathname.match(/^\/docs\/([^\/]+)\/(?:(.+?)\/)?README\.md$/);
            if (!m) return null;
            var api = '/api/docs?module=' + encodeURIComponent(m[1]);
            if (m[2]) api += '&page=' + encodeURIComponent(m[2]);
            return api;
        } catch (e) { return null; }
    }

    function redirectToLogin() {
        window.location.href = '/';
    }

    var _fetch = window.fetch;
    window.fetch = function (url, opts) {
        var api = readmeToApi(url);
        return _fetch(api || url, opts).then(function (r) {
            if (r.status === 401) { redirectToLogin(); }
            return r;
        });
    };

    var _open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        var api = readmeToApi(url);
        var xhr = this;
        xhr.addEventListener('load', function () {
            if (xhr.status === 401) { redirectToLogin(); }
        });
        return _open.apply(this, api ? [method, api].concat(Array.prototype.slice.call(arguments, 2)) : arguments);
    };
})();

window.$docsify = {
    name: 'AtroCore Docs',
    homepage: 'README.md',
    loadSidebar: true,
    alias: {
        '/(.*/)?_sidebar\\.md': window.location.origin + '/api/docs?module=navigation'
    },
    routes: {
        '/': function(route, matched, next) {
            fetch('/api/docs?module=README').then(r => r.text()).then(next);
        },
        '/([^/]+)/(.+?)/?': function(route, matched, next) {
            fetch('/api/docs?module=' + encodeURIComponent(matched[1]) + '&page=' + encodeURIComponent(matched[2]))
                .then(r => r.text()).then(next);
        },
        '/([^/]+)/?': function(route, matched, next) {
            fetch('/api/docs?module=' + encodeURIComponent(matched[1]))
                .then(r => r.text()).then(next);
        },
    },
    markdown: {
        renderer: {
            blockquote: function(quote) {
                var m = /^<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*([\s\S]*?)<\/p>/i.exec(quote);
                if (m) {
                    var cls = m[1].toLowerCase();
                    var body = m[2].trim();
                    return '<blockquote class="' + cls + '"><p>' + body + '</p></blockquote>\n';
                }
                return '<blockquote>' + quote + '</blockquote>\n';
            }
        }
    },
    sidebarDisplayLevel: 1,
    search: null,
    plugins: [
        function (hook) {
            var tocEl = null;
            var scrollHandler = null;

            function buildToc() {
                if (!tocEl) {
                    tocEl = document.createElement('div');
                    tocEl.id = 'page-toc';
                    var sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.parentNode.insertBefore(tocEl, sidebar.nextSibling);
                    } else {
                        document.body.appendChild(tocEl);
                    }
                }

                var headings = Array.from(document.querySelectorAll('.markdown-section h2, .markdown-section h3, .markdown-section h4, .markdown-section h5, .markdown-section h6'));

                if (!headings.length) {
                    tocEl.hidden = true;
                    document.body.classList.remove('has-toc');
                    return;
                }

                document.body.classList.add('has-toc');

                var ul = document.createElement('ul');
                headings.forEach(function (h) {
                    var li = document.createElement('li');
                    li.className = 'toc-' + h.tagName.toLowerCase();
                    var a = document.createElement('a');
                    a.href = '#' + h.id;
                    a.textContent = h.textContent.replace(/\s*#\s*$/, '').trim();
                    a.dataset.id = h.id;
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    li.appendChild(a);
                    ul.appendChild(li);
                });

                tocEl.hidden = false;
                tocEl.innerHTML = '';
                tocEl.appendChild(ul);
                updateActive();
            }

            function updateActive() {
                if (!tocEl || tocEl.hidden) return;
                var headings = Array.from(document.querySelectorAll('.markdown-section h2, .markdown-section h3, .markdown-section h4, .markdown-section h5, .markdown-section h6'));
                var active = null;

                for (var i = 0; i < headings.length; i++) {
                    if (headings[i].getBoundingClientRect().top <= 80) {
                        active = headings[i];
                    }
                }

                tocEl.querySelectorAll('a').forEach(function (a) {
                    a.classList.remove('active');
                });

                if (active) {
                    var link = tocEl.querySelector('a[data-id="' + active.id + '"]');
                    if (link) {
                        link.classList.add('active');
                        link.scrollIntoView({ block: 'nearest' });
                    }
                }
            }

            hook.ready(function () {
                if (!document.getElementById('toc-toggle')) {
                    var btn = document.createElement('button');
                    btn.id = 'toc-toggle';
                    btn.setAttribute('aria-label', 'Toggle TOC');
                    btn.innerHTML = '<div class="toc-toggle-btn"><span></span><span></span><span></span></div>';
                    btn.addEventListener('click', function () {
                        document.body.classList.toggle('toc-close');
                    });
                    document.body.appendChild(btn);
                }
            });

            hook.doneEach(function () {
                if (scrollHandler) {
                    window.removeEventListener('scroll', scrollHandler);
                }
                buildToc();
                scrollHandler = updateActive;
                window.addEventListener('scroll', scrollHandler, { passive: true });
            });
        }
    ],
};
