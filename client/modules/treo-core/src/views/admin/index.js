/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
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

            $('footer').removeClass('is-collapsed not-collapsed');
        }
    })
);
