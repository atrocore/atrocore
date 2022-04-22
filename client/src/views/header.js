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

Espo.define('views/header', 'view', function (Dep) {

    return Dep.extend({

        template: 'header',

        events: _.extend({
            'click a:not([data-action])': function (e) {
                let path = e.currentTarget.getAttribute("href");
                e.preventDefault();
                this.getRouter().checkConfirmLeaveOut(function () {
                    this.getRouter().navigate(path, {trigger: true});
                }.bind(this), this, false);
            }
        }, Dep.prototype.events),

        data: function () {
            let data = {};
            if ('getHeader' in this.getParentView()) {
                data.header = this.getParentView().getHeader();
            }
            data.scope = this.scope || this.getParentView().scope;
            data.items = this.getItems();
            data.isXsSingleRow = this.options.isXsSingleRow;

            if (this.model && !this.model.isNew()) {
                data.overviewFilters = this.getOverviewFiltersList().map(filter => filter.name);
            }

            if ((data.items.buttons || []).length < 2) {
                data.isHeaderAdditionalSpace = true;
            }

            return data;
        },

        setup: function () {
            this.scope = this.options.scope;
            if (this.model) {
                this.listenTo(this.model, 'after:save', () => {
                    if (this.isRendered()) {
                        this.reRender();
                    }
                });

                if (!this.model.isNew()) {
                    this.createOverviewFilters();
                }
            }
        },

        afterRender: function () {

        },

        getItems: function () {
            var items = this.getParentView().getMenu() || {};

            return items;
        },

        getOverviewFiltersList: function () {
            let result = [
                {
                    name: "fieldFilter",
                    options: ["filled", "empty"]
                }
            ];

            if (this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                result.push({
                    name: "languageFilter",
                    options: ['main'].concat(this.getConfig().get('inputLanguageList'))
                });
            }

            return result;
        },

        createOverviewFilters() {
            this.getModelFactory().create(null, model => {
                this.getOverviewFiltersList().forEach(filter => {
                    this.createOverviewFilter(filter, model);
                });
            });
        },

        createOverviewFilter(filter, model) {
            if (!this.getStorage().get(filter.name, 'OverviewFilter')) {
                this.getStorage().set(filter.name, 'OverviewFilter', filter.options);
            }

            model.set(filter.name, this.getStorage().get(filter.name, 'OverviewFilter'));

            let translatedOptions = {};
            filter.options.forEach(option => {
                translatedOptions[option] = this.getLanguage().translateOption(option, filter.name, 'Global');
            });

            this.createView(filter.name, 'views/fields/multi-enum', {
                el: `${this.options.el} .field[data-name="${filter.name}"]`,
                name: filter.name,
                mode: 'edit',
                model: model,
                dragDrop: false,
                params: {
                    options: filter.options,
                    translatedOptions: translatedOptions
                }
            }, view => {
                this.listenTo(model, `change:${filter.name}`, () => {
                    let values = [];
                    filter.options.forEach(option => {
                        if (model.get(filter.name).includes(option)) {
                            values.push(option);
                        }
                    });

                    if (values.length === 0) {
                        values = [filter.options[0]];
                    }

                    this.getStorage().set(filter.name, 'OverviewFilter', values);
                    this.model.trigger('overview-filters-changed');

                    model.set(filter.name, values);
                    view.reRender();
                });
                view.render();
            });
        },

    });
});

