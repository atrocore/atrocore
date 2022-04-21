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

Espo.define('views/fields/dropdown-enum', 'view',
    Dep => Dep.extend({

        template: 'fields/dropdown-enum/base',

        optionsList: [],

        modelKey: null,

        storageKey: null,

        events: _.extend({
            'click .action[data-action="saveFilter"]': function (e) {
                let el = $(e.currentTarget);
                let name = el.data('name');
                let option = this.optionsList.find(option => option.name === name) || {};
                if (!option.selectable) {
                    e.stopPropagation();
                }
                this.actionSaveFilter(name);
            }
        }, Dep.prototype.events),

        data() {
            return {
                name: this.name,
                options: this.optionsList,
                selected: this.getSelectedLabel()
            }
        },

        setup() {
            this.name = this.options.name || this.name;
            this.scope = this.options.scope || this.scope || this.model.name;

            console.log(this.options)

            this.optionsList = this.options.optionsList || this.optionsList;
            this.prepareOptionsList();

            this.storageKey = this.options.storageKey || this.storageKey;
            if (this.storageKey) {
                let selected = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).selected;
                if (this.optionsList.find(option => option.name === selected)) {
                    this.selected = selected;
                }
            }
            this.selected = this.selected || (this.optionsList.find(option => option.selectable) || {}).name;
            this.modelKey = this.options.modelKey || this.modelKey;
            this.setDataToModel({[this.name]: this.selected});

            this.listenTo(this, 'after:render', () => {
                this.options.hidden ? this.hide() : this.show();
                this.createFields();
            });
        },

        createFields() {
            this.optionsList.forEach(option => {
                if (option.name && option.field && (option.type || option.view)) {
                    let views = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).views || {};
                    let dataKeys = this.getFieldManager().getActualAttributeList(option.type, option.name);
                    dataKeys.forEach(key => {
                        let value = typeof views[key] !== 'undefined' ? views[key] : option.default;
                        this.setDataToModel({[key]: value}, true);
                    });

                    let view = option.view || this.getFieldManager().getFieldView(option.type) || 'views/fields/base';
                    this.createView(option.name, view, {
                        el: `${this.options.el} .dropdown-enum-menu li a .field[data-name="${option.name}"]`,
                        model: this.model,
                        name: option.name,
                        inlineEditDisabled: true,
                        mode: 'edit',
                        label: option.label
                    }, view => {
                        view.render();
                    });
                }
            });
        },

        prepareOptionsList() {
            this.optionsList.forEach(option => {
                option.label = option.label || this.getLanguage().translateOption(option.name, this.name, 'Global');
                if (option.field) {
                    option.html = `<div class="field" data-name="${option.name}"></div>`;
                }
                option.html = option.html || option.label;
            });
        },

        getSelectedLabel() {
            return (this.optionsList.find(option => option.name === this.selected) || {}).label;
        },

        actionSaveFilter(name) {
            let option = this.optionsList.find(option => option.name === name) || {};
            if (option.selectable) {
                this.selected = name;
            }
            if (this.storageKey) {
                let previousFilters = this.getStorage().get(this.storageKey, this.scope) || {};
                let currentFilterData = previousFilters[this.name] || {};
                currentFilterData.selected = this.selected;
                if (option.field) {
                    let field = this.getView(name);
                    if (field) {
                        let fieldData = field.fetch();
                        this.setDataToModel(fieldData);
                        currentFilterData.views = _.extend({}, currentFilterData.views, fieldData);
                    }
                }
                this.getStorage().set(this.storageKey, this.scope, _.extend(previousFilters, {[this.name]: currentFilterData}));
            }
            this.setDataToModel({[this.name]: this.selected});
            this.reRender();

            this.model.trigger('overview-filters-changed', name);
        },

        setDataToModel(data, isField) {
            if (Espo.Utils.isObject(data)) {
                Object.keys(data).forEach(item => {
                    if (this.modelKey) {
                        this.model[this.modelKey] = _.extend({}, this.model[this.modelKey] , {[item]: data[item]});
                    } else {
                        this.model.set({[item]: data[item]}, {silent: true});
                    }
                    if (isField) {
                        this.model.set({[item]: data[item]}, {silent: true});
                    }
                });
            }
        },

        getParentCell() {
            return this.$el.parent();
        },

        hide() {
            let cell = this.getParentCell();
            cell.addClass('hidden-cell');
        },

        show() {
            let cell = this.getParentCell();
            cell.removeClass('hidden-cell');
        }

    })
);