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
 */

Espo.define('treo-core:views/queue-manager/badge', 'view',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/badge',

        events: {
            'click a[data-action="showQueue"]': function (e) {
                if (!this.hasView('panel')) {
                    this.showQueue();
                } else {
                    this.closeQueue();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeQueue();
            }
        },

        afterRender() {
            this.listenTo(Backbone, 'showQueuePanel', () => {
                if (this.checkConditions()) {
                    this.showQueue();
                }
            });
        },

        showQueue() {
            this.closeQueue();

            this.$el.addClass('open');

            this.createView('panel', 'treo-core:views/queue-manager/panel', {
                el: `${this.options.el} .queue-panel-container`
            }, view => {
                this.listenTo(view, 'closeQueue', () => {
                    this.closeQueue();
                });
                view.render();
            });

            $(document).on('mouseup.queue', function (e) {
                let container = this.$el.find('.queue-panel-container');
                if (!container.is(e.target) && container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    this.closeQueue();
                }
            }.bind(this));
        },

        closeQueue() {
            this.$el.removeClass('open');

            if (this.hasView('panel')) {
                this.clearView('panel');
            }

            $(document).off('mouseup.queue');
        },

        checkConditions() {
            return (this.options.intervalConditions || []).every(item => {
                if (typeof item === 'function') {
                    return item();
                }
                return false;
            });
        }

    })
);
