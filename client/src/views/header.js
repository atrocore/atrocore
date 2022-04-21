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
                data.overviewFilters = this.getOverviewFiltersList();
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
                'fieldsFilter'
            ];

            if (this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                result.push('localesFilter');
            }

            return result;
        },

        createOverviewFilters() {
            this.getModelFactory().create(null, model => {
                this.getOverviewFiltersList().forEach(name => {
                    let method = "createOverview" + name.charAt(0).toUpperCase() + name.slice(1);
                    if (typeof this[method] === "function") {
                        this[method](model);
                    }
                });

                // this.createOverviewFieldsFilter(model);

                // if (this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                //     // this.createView('localesFilter', 'views/fields/overview-locales-filter', {
                //     //     el: `${this.options.el} .field[data-name="localesFilter"]`,
                //     //     model: model,
                //     //     entityModel: this.model,
                //     //     name: 'localesFilter'
                //     // }, view => {
                //     //     view.render();
                //     // });
                // }
            });
        },

        createOverviewFieldsFilter(model) {
            if (!this.getStorage().get('fieldsFilter', 'OverviewFilter')) {
                this.getStorage().set('fieldsFilter', 'OverviewFilter', ['filled', 'empty']);
            }
            model.set('fieldsFilter', this.getStorage().get('fieldsFilter', 'OverviewFilter'));
            this.createView('fieldsFilter', 'views/fields/multi-enum', {
                el: `${this.options.el} .field[data-name="fieldsFilter"]`,
                name: 'fieldsFilter',
                mode: 'edit',
                model: model,
                entityModel: this.model,
                prohibitedEmptyValue: true,
                params: {
                    options: ['filled', 'empty'],
                    translatedOptions: {
                        "filled": this.getLanguage().translateOption('allFields', 'fieldsFilter', 'Global'),
                        "empty": this.getLanguage().translateOption('empty', 'fieldsFilter', 'Global')
                    }
                }
            }, view => {
                this.listenTo(model, 'change:fieldsFilter', () => {
                    this.getStorage().set('fieldsFilter', 'OverviewFilter', model.get('fieldsFilter'));
                    this.model.trigger('overview-filters-changed');
                });
                view.render();
            });
        },

        createOverviewLocalesFilter(model) {
            // if (!this.getStorage().get('localesFilter', 'OverviewFilter')) {
            //     this.getStorage().set('localesFilter', 'OverviewFilter', 'allFields');
            // }
            // model.set('localesFilter', this.getStorage().get('fieldsFilter', 'OverviewFilter'));

            // 1px solid #eaeaea
            model.set('localesFilter', ['1', '2', '3']);
            this.createView('localesFilter', 'views/fields/multi-enum', {
                el: `${this.options.el} .field[data-name="localesFilter"]`,
                name: 'localesFilter',
                mode: 'edit',
                model: model,
                entityModel: this.model,
                prohibitedEmptyValue: true,
                placeholder: "qwe",
                params: {
                    placeholder: "qwe",
                    options: ['1', '2', '3'],
                    translatedOptions: {
                        "1": 'Main',
                        "2": 'de_DE',
                        "3": 'en_US',
                        // "emptyAndRequired": this.getLanguage().translateOption('emptyAndRequired', 'fieldsFilter', 'Global')
                    }
                }
            }, view => {
                // this.listenTo(model, 'change:fieldsFilter', () => {
                //     this.getStorage().set('fieldsFilter', 'OverviewFilter', model.get('fieldsFilter'));
                //     this.model.trigger('overview-filters-changed');
                // });
                view.render();
            });
        },

    });
});

