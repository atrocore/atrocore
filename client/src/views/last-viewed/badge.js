/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/last-viewed/badge', 'view', function (Dep) {
    return Dep.extend({
        template: 'last-viewed/badge',

        events: {
            'click a[data-action="showLastViewed"]': function (e) {
                if (!this.hasView('panel')) {
                    this.showLastViewed();
                } else {
                    this.closeLastViewed();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeLastViewed();
            }
        },

        showLastViewed: function () {
            this.closeLastViewed();

            this.$el.addClass('open');

            var $container = $('<div>').attr('id', 'notifications-panel');

            $container.appendTo(this.$el.find('.last-viewed-panel-container'));

            this.createView('panel', 'views/last-viewed/panel', {
                el: `${this.options.el} .last-viewed-panel-container`
            }, view => {
                this.listenTo(view, 'closeLastViewed', () => {
                    this.closeLastViewed();
                });
                view.render();
            });

            $(document).on('mouseup.last-viewed', function (e) {
                let container = this.$el.find('.last-viewed-panel-container');
                if (!container.is(e.target) && container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    this.closeLastViewed();
                }
            }.bind(this));
        },

        closeLastViewed: function () {
            this.$el.removeClass('open');

            if (this.hasView('panel')) {
                this.clearView('panel');
            }

            $(document).off('mouseup.last-viewed');
        },
    })
});