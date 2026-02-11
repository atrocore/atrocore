/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/master', ['class-replace!treo-core:views/site/master', 'color-converter'],
    (Dep, ColorConverter) => Dep.extend({

        styleVariableMap: {
            navigationManuBackgroundColor: '--nav-menu-background',
            navigationMenuFontColor: '--nav-font-color',
            linkFontColor: '--link-color',
            primaryColor: '--primary-color',
            secondaryColor: '--secondary-color',
            primaryFontColor: '--primary-font-color',
            secondaryFontColor: '--secondary-font-color',
            labelColor: '--label-color',
            anchorNavigationBackground: '--anchor-nav-background',
            iconColor: '--icon-color',
            primaryBorderColor: '--primary-border-color',
            secondaryBorderColor: '--secondary-border-color',
            panelTitleColor: '--panel-title-color',
            headerTitleColor: '--header-title-color',
            success: '--success',
            notice: '--notice',
            information: '--information',
            error: '--error',
            actionIconColor: '--action-icon-color',
            statusIconColor: '--status-icon-color',
            highlightingColorForRequired: '--highlighting-color-for-required',
            highlightingColorForDataQuality: '--highlighting-color-for-data-quality',
            toolbarBackgroundColor: '--toolbar-background-color',
            toolbarFontColor: '--toolbar-font-color',
        },

        rgbVariables: ['navigationManuBackgroundColor', 'navigationMenuFontColor'],

        events: {
            'click #title-bar [data-action=copyUrl]': function () {
                this.copyToClipboard(window.location.href);
                this.notify('copiedToClipboard', 'success');
            },
            'click #title-bar [data-action=openWindow]': function (e) {
                e.preventDefault();

                const screenX = window.screenX;
                const screenY = window.screenY;
                const width = window.outerWidth;
                const height = window.outerHeight;

                this.openWindow(window.location.href, screenX, screenY, width, height);
            },
            'click #title-bar [data-action=reload]': function () {
                window.location.reload();
            },
            'click #title-bar [data-action=goBack]': function () {
                window.history.back();
            },
            'click #title-bar [data-action=goForward]': function () {
                window.history.forward();
            },
            'click #title-bar [data-action=splitHorizontal]': function () {
                const workArea = this.getScreenWorkArea();

                this.moveCurrentWindow(workArea.screenLeft, workArea.screenTop, workArea.screenWidth / 2, workArea.screenHeight);
                this.openWindow(window.location.href, workArea.screenLeft + workArea.screenWidth / 2, workArea.screenTop, workArea.screenWidth / 2, workArea.screenHeight);
            },
            'click #title-bar [data-action=splitVertical]': function () {
                const workArea = this.getScreenWorkArea();

                this.moveCurrentWindow(workArea.screenLeft, workArea.screenTop, workArea.screenWidth, workArea.screenHeight / 2);
                this.openWindow(window.location.href, workArea.screenLeft, workArea.screenTop + workArea.screenHeight / 2, workArea.screenWidth, workArea.screenHeight / 2);
            },
            'click #title-bar [data-action=expand]': function () {
                const workArea = this.getScreenWorkArea();
                this.moveCurrentWindow(workArea.screenLeft, workArea.screenTop, workArea.screenWidth, workArea.screenHeight);
            },
        },

        data: function () {
            return {
                faviconUrl: this.getFavicon()
            };
        },

        setup: function () {
            if (!sessionStorage.tabId) {
                sessionStorage.tabId = Math.random().toString(36).substr(2, 25);
            }

            if (navigator.clearAppBadge) {
                navigator.clearAppBadge();
            }

            window.addEventListener('appinstalled',  () => {
                if ('windowControlsOverlay' in navigator && !navigator.windowControlsOverlay.visible) {
                    this.createView('hideTitleModal', 'views/modals/hide-title-bar', {}, view => {
                        view.render();
                    });
                }
            });

            if (window.navigation) {
                window.navigation.addEventListener("navigatesuccess", () => {
                    this.updateNavButtons();
                });
            }

            this.listenToOnce(this, 'remove', () => {
                if (navigator.clearAppBadge) {
                    navigator.clearAppBadge();
                }
            });
        },

        getScreenWorkArea: function () {
            const screenLeft = window.screen.availLeft ?? 0;
            const screenTop = window.screen.availTop ?? 0;
            const screenWidth = window.screen.availWidth;
            const screenHeight = window.screen.availHeight;

            return {screenLeft, screenTop, screenWidth, screenHeight};
        },

        moveCurrentWindow(x, y, w, h) {
            window.resizeTo(w, h);
            window.moveTo(x, y);
        },

        openWindow(url, x, y, w, h) {
            return window.open(url, "_blank", `left=${x},top=${y},width=${w},height=${h},noopener`);
        },

        updateNavButtons() {
            if (!window.navigation || !('canGoBack' in window.navigation)) {
                return;
            }

            const goBackBtn = $('#title-bar [data-action=goBack]');
            const goForwardBtn = $('#title-bar [data-action=goForward]');

            goBackBtn.attr('disabled', !window.navigation.canGoBack);
            goForwardBtn.attr('disabled', !window.navigation.canGoForward);
        },

        initStyleVariables(style) {
            if ($(":root").length > 0) {
                if (style) {
                    (Object.keys(this.styleVariableMap) || []).forEach(param => {
                        if (style[param]) {
                            $(":root")[0].style.setProperty(this.styleVariableMap[param], style[param]);
                            if (this.rgbVariables.includes(param)) {
                                $(":root")[0].style.setProperty(this.styleVariableMap[param] + '-rgb', this.hexToRgb(style[param]))
                            }
                        }
                    });
                }
            }

            const rootStyle = getComputedStyle(document.documentElement);
            const titleBarColor = rootStyle.getPropertyValue(this.styleVariableMap['toolbarBackgroundColor']);
            const themeColorEl = $('head meta[name="theme-color"]');

            if (themeColorEl.length) {
                themeColorEl.attr('content', titleBarColor);
            } else {
                $('head').append(`<meta name="theme-color" content="${titleBarColor}">`);
            }
        },

        removeStyleVariables() {
            if ($(":root").length > 0) {
                (Object.keys(this.styleVariableMap) || []).forEach(param => {
                    $(":root")[0].style.setProperty(this.styleVariableMap[param], '');
                    if (this.rgbVariables.includes(param)) {
                        $(":root")[0].style.setProperty(this.styleVariableMap[param] + '-rgb', '')
                    }
                });
            }
        },

        getIconFilter: function(color) {
            if (!color) return null;

            let colorConverter = new ColorConverter(color);
            return colorConverter.solve().filter;
        },

        afterRender() {
            this.updateNavButtons();

            let style = this.getThemeManager().getStyle();
            this.initStyleVariables(style);

            window.Tooltip.initWithinNode(document.body);
            window.Dropdown.initWithinNode(document.body);

            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    window.Tooltip.processMutation(mutation);
                    window.Dropdown.processMutation(mutation);
                    window.Popover.processMutation(mutation);
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['title', 'data-title-link'],
                characterData: false
            });
        },

        hexToRgb(hex) {
            hex = hex.replace(/^#/, '');
            if (hex.length === 3) {
                hex = hex.split('').map(char => char + char).join('');
            }
            let bigint = parseInt(hex, 16);
            let r = (bigint >> 16) & 255;
            let g = (bigint >> 8) & 255;
            let b = bigint & 255;

            return `${r}, ${g}, ${b}`;
        }


    })
);


