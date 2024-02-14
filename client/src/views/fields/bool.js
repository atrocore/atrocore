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

Espo.define('views/fields/bool', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'bool',

        listTemplate: 'fields/bool/list',

        detailTemplate: 'fields/bool/detail',

        editTemplate: 'fields/bool/edit',

        searchTemplate: 'fields/bool/search',

        validations: [],

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.valueIsSet = this.model.has(this.name);
            return data;
        },

        fetch: function () {
            var value = this.$el.find('input[name=' + this.name + ']').is(":checked");
            var data = {};
            data[this.name] = value;
            return data;
        },

        clearSearch: function () {
            this.$el.find('input[name=' + this.name + ']').prop('checked', true);
        },

        fetchSearch: function () {
            var data = {
                type: this.$element.get(0).checked ? 'isTrue' : 'isFalse',
            };
            return data;
        },

        populateSearchDefaults: function () {
            this.$element.get(0).checked = true;
        },

        createQueryBuilderFilter() {
            const scope = this.model.urlRoot;

            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', scope),
                type: 'boolean',
                operators: [
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: (rule, inputName) => {
                    if (!rule || !inputName) {
                        return '';
                    }
                    this.filterValue = false;
                    this.getModelFactory().create(null, model => {
                        this.createView(inputName, 'views/fields/bool', {
                            name: 'value',
                            el: `#${rule.id} .field-container`,
                            model: model,
                            mode: 'edit'
                        }, view => {
                            this.listenTo(view, 'change', () => {
                                this.filterValue = model.get('value');
                                rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                            });
                            this.renderAfterEl(view, `#${rule.id} .field-container`);
                        });
                    });
                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: (rule) => {
                    return this.filterValue;
                }
            };
        },

    });
});

