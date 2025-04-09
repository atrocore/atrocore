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
            statusIconColor: '--status-icon-color',
            highlightingColorForRequired: '--highlighting-color-for-required',
            highlightingColorForDataQuality: '--highlighting-color-for-data-quality'
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

            const getTooltipContent = (el) => {
                const titleText = el.getAttribute('data-original-title') || el.getAttribute('title');
                let content = `<div>${titleText}</div>`;

                const titleLink = el.getAttribute('data-original-title-link') || el.getAttribute('data-title-link');
                if (titleLink) {
                    content += `<div class="tippy-footer"><a href="${titleLink}" target="_blank"><u>Read more</u></a></div>`;
                }
                return content;
            };

            const initializeTooltips = (node = document) => {
                node.querySelectorAll('[title]').forEach(el => {
                    if (!el.getAttribute('data-original-title')) {
                        el.setAttribute('data-original-title', el.getAttribute('title'));
                    }

                    if (el.getAttribute('data-title-link') && !el.getAttribute('data-original-title-link')) {
                        el.setAttribute('data-original-title-link', el.getAttribute('data-title-link'));
                    }

                    if (el.dataset.tippy) {
                        if (el._tippy) {
                            el._tippy.setContent(getTooltipContent(el));
                        }
                    } else {
                        window.tippy(el, {
                            appendTo: () => document.body,
                            maxWidth: 350,
                            content: getTooltipContent(el),
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

            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    const el = mutation.target;

                    if (el.getAttribute('title')) {
                        el.setAttribute('data-original-title', el.getAttribute('title'));
                    }
                    if (el.getAttribute('data-title-link')) {
                        el.setAttribute('data-original-title-link', el.getAttribute('data-title-link'));
                    }
                    if (el.dataset.tippy && el._tippy) {
                        el._tippy.setContent(getTooltipContent(el));
                        el.removeAttribute('title');
                        el.removeAttribute('data-title-link');
                    } else {
                        initializeTooltips(el);
                    }
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['title', 'data-title-link']
            });
        }

    })
);


