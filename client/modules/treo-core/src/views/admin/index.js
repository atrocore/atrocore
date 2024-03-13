/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/index', 'class-replace!treo-core:views/admin/index',
    Dep => Dep.extend({

        template: 'treo-core:admin/index',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let navbar = $('.navbar-nav.navbar-right');
            let pageHeader = $('.page-header');
            let container = $('.admin-tables-container');
            let rightColumn = $('.admin-right-column');

            if (container.length) {
                let prevScrollTop = 0;
                let primaryContainerPosition = container.get(0).getBoundingClientRect();

                $(window).on('scroll', () => {
                    if ($(window).width() >= 992 && container.height() < rightColumn.height()) {
                        let currentScrollTop = $(window).scrollTop();
                        let containerPosition = container.get(0).getBoundingClientRect();

                        if (prevScrollTop < currentScrollTop) {
                            if (containerPosition.bottom < $(window).height()) {
                                container.removeClass('scrolled');
                                container.addClass('fixed-bottom');

                                container.css({width: container.parent().width() + 'px', top: ''});
                            } else if (container.hasClass('fixed-top')){
                                container.addClass('scrolled');
                                container.removeClass('fixed-top');

                                container.css({top: currentScrollTop + 'px'});
                            }
                        } else {
                            if (container.hasClass('fixed-bottom')) {
                                let offset = container.offset().top;

                                container.removeClass('fixed-bottom');
                                container.addClass('scrolled');

                                container.css({top: offset - primaryContainerPosition.top + 'px'});
                            } else if (containerPosition.top > navbar.height()) {
                                container.removeClass('scrolled');
                                container.css({top: ''});

                                container.addClass('fixed-top');
                                container.css({width: container.parent().width() + 'px'});
                            }

                            if (currentScrollTop < navbar.height() + pageHeader.height()) {
                                container.removeClass('fixed-top');
                                container.css({top: ''});
                            }
                        }

                        prevScrollTop = currentScrollTop;
                    }
                });

                $(window).on('resize', () => {
                    if ($(window).width() < 992) {
                        container.removeClass('fixed-top fixed-bottom scrolled');
                        container.css({top: '', width: ''});
                    } else {
                        container.css({width: container.parent().width() + 'px'});
                    }
                });
            }
        }
    })
);
