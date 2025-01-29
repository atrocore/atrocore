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

Espo.define('views/fields/grouped-enum', ['views/fields/enum'], function (Dep) {

    return Dep.extend({

        editTemplate: 'fields/grouped-enum/edit',

        translatedGroups: null,

        data: function () {
            const data = Dep.prototype.data.call(this);

            data.translatedGroups = this.translatedGroups
            data.prohibitedEmptyValue = this.prohibitedEmptyValue
            data.groups = this.getActiveGroups()

            return data;
        },

        getActiveGroups() {
            const groups = {}
            Object.keys(this.params.groups).forEach(group => {
                const options = (this.params.groups[group] || []).filter(opt => this.params.options.includes(opt))
                if (options.length) {
                    groups[group] = options
                }
            })
            return groups
        },

        setupGroups() {
            this.params.groups = this.params.groups || this.model.getFieldParam(this.name, 'groups') || {}

            const options = []
            Object.keys(this.params.groups).forEach(group => {
                options.push(...this.params.groups[group])
            })
            this.params.options = options
        },

        setup: function () {
            this.setupGroups()

            if ('translatedGroups' in this.params) {
                this.translatedGroups = this.params.translatedGroups;
            }

            Dep.prototype.setup.call(this);
        },

        setupTranslation: function () {
            Dep.prototype.setupTranslation.call(this)

            if (this.params.groupTranslation) {
                this.translatedGroups = this.translate(...this.params.groupTranslation.split('.').reverse())
            }

            if (this.translatedGroups == null || typeof this.translatedGroups != 'object') {
                this.translatedGroups = this.translate(this.name, 'groups', this.model?.name)
            }
        },

        getSearchOptions() {
            const options = [];
            const groups = []
            Object.keys(this.params.groups).forEach((group) => {
                groups.push({value: group, label: this.translatedGroups?.[group] || group});

                (this.params.groups[group] || []).forEach(value => {
                    var label = this.getLanguage().translateOption(value, this.name, this.model?.name);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[value];
                        }
                    }
                    options.push({
                        value: value,
                        label: label,
                        group: group
                    });
                })
            }, this);

            return {
                options: options,
                optgroups: groups,
                optgroupLabelField: 'label',
                optgroupValueField: 'value',
                optgroupField: 'group',
            }
        },

    });
});

