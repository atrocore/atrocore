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

Espo.define('views/record/tree-panel/category-search', 'view',
    Dep => Dep.extend({

        template: 'record/tree-panel/category-search',

        data() {
            return {
                scope: this.scope
            }
        },

        setup() {
            this.scope = this.options.scope || this.scope;
        },

        afterRender() {
            if (this.el) {
                this.$el.find('input').autocomplete({
                    serviceUrl: function () {
                        return this.getAutocompleteUrl();
                    }.bind(this),
                    paramName: 'q',
                    minChars: 1,
                    autoSelectFirst: true,
                    transformResult: function (json) {
                        let response = JSON.parse(json);
                        let list = [];
                        response.list.forEach(category => {
                            let item = Espo.Utils.cloneDeep(category);
                            item.value = item.name;
                            list.push(item);
                        });
                        return {
                            suggestions: list
                        };
                    }.bind(this),
                    onSelect: function (category) {
                        this.$el.find('input').val('');
                        this.trigger('category-search-select', category);
                    }.bind(this)
                });
            }
        },

        getAutocompleteUrl() {
            return this.scope + '?sortBy=createdAt';
        }

    })
);