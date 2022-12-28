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

Espo.define('treo-core:views/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'treo-core:search/filter',

        pinned: false,

        events: {
            'click a[data-action="pinFilter"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                this.pinned = !this.pinned;

                this.trigger('pin-filter', this.pinned);
            },
        },

        data: function () {
            let isPinEnabled = true;

            if (this.getParentView() && this.getParentView().getParentView() && this.getParentView().getParentView()) {
                const parent =  this.getParentView().getParentView();

                if (('layoutName' in parent) && parent.layoutName === 'listSmall') {
                    isPinEnabled = false;
                }
            }

            return {
                generalName: this.generalName,
                name: this.name,
                scope: this.model.name,
                notRemovable: this.options.notRemovable,
                isPinEnabled: isPinEnabled,
                pinned: this.pinned
            };
        },

        setup: function () {
            var newName = this.name = this.options.name;
            this.generalName = newName.split('-')[0];
            var type = this.model.getFieldType(this.generalName);

            this.pinned = this.options.pinned;

            if (type) {
                var viewName = this.model.getFieldParam(this.generalName, 'view') || this.getFieldManager().getViewName(type);

                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    defs: {
                        name: this.generalName,
                    },
                    searchParams: this.options.params,
                });
            }
        }
    });
});