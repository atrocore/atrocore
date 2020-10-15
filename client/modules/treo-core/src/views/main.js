

/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://treolabs.com
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
 */

Espo.define('treo-core:views/main', 'class-replace!treo-core:views/main', function (Dep) {

    return Dep.extend({

        setupFinal() {
            Dep.prototype.setupFinal.call(this);

            this.bindFixedHeaderOnScroll();
        },

        buildHeaderHtml: function (arr) {
            var a = [];
            arr.forEach(function (item) {
                a.push('<span>' + item + '</span>');
            }, this);

            return '<div class="header-breadcrumbs">' + a.join('<span class="breadcrumb-separator"> &rsaquo; </span>') + '</div>';
        },

        bindFixedHeaderOnScroll() {
            let $window = $(window);
            this.listenToOnce(this, 'remove', () => {
                $window.off('scroll.fixed-header')
            });
            this.listenTo(this, 'after:render', () => {
                $window.off('scroll.fixed-header');
                $window.on('scroll.fixed-header', () => {
                    let scrollTop = $window.scrollTop();
                    let header = this.$el.find('.header-breadcrumbs');
                    let navBarRight = $('#header .navbar-right');
                    let width = $('#header ul.navbar-right > li').get().reduce((prev, curr) => {
                        return prev - $(curr).outerWidth()
                    }, navBarRight.outerWidth() - 30);
                    if (scrollTop > this.$el.find('.page-header').outerHeight() && !$('#header .navbar .menu').hasClass('open-menu')) {
                        header.addClass('fixed-header-breadcrumbs')
                            .css('width', width + 'px');
                    } else {
                        header.removeClass('fixed-header-breadcrumbs')
                            .css('width', 'auto');
                    }
                });
            });
        }

    });
});