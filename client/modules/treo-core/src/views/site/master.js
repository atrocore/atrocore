/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/master', 'class-replace!treo-core:views/site/master',
    Dep => Dep.extend({

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
            navigationIconColor: '--navigation-icon-color',
            statusIconColor: '--status-icon-color',
        },

        afterRender() {
            if ($(":root").length > 0) {
                let style = this.getThemeManager().getStyle();
                if (style) {
                    (Object.keys(this.styleVariableMap) || []).forEach(param => {
                        if (style[param]) {
                            $(":root")[0].style.setProperty(this.styleVariableMap[param], style[param]);
                        }
                    });
                }
            }

            const initializeTooltips = () => {
                document.querySelectorAll('[title]').forEach(el => {
                    if (!el.dataset.tippy) {
                        window.tippy(el, {
                            appendTo: () => document.body,
                            maxWidth: 350,
                            content: ref => {
                                let html = `<div>${ref.getAttribute('title')}</div>`;

                                if (ref.getAttribute('data-title-link')) {
                                    html += `<div class="tippy-footer"><a href="${ref.getAttribute('data-title-link')}" target="_blank"><u>${this.translate('Read more')}</u></a></div>`;
                                }

                                return html;
                            },
                            allowHTML: true,
                            trigger: 'mouseenter',
                            delay: [500, 0],
                            touch: ['hold', 500],
                            hideOnClick: true,
                            interactive: true,
                            onShow(instance) {
                                document.querySelectorAll('[data-tippy-root]').forEach(tip => {
                                    if (tip !== instance.popper) {
                                        tip._tippy.hide();
                                    }
                                });
                            }
                        });
                        el.setAttribute('data-tippy', 'true');
                        el.removeAttribute('title');
                        el.removeAttribute('data-title-link');
                    }
                });
            };

            initializeTooltips();

            const observer = new MutationObserver(initializeTooltips);
            observer.observe(document.body, { childList: true, subtree: true });
        }

    })
);


