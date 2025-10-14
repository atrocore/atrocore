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

Espo.define('views/fields/classifications-single', ['views/fields/link', 'views/fields/classifications'],
    (Dep, Classifications) => Dep.extend({

        idName: 'classificationsId',

        nameName: 'classificationsName',

        originalIdName: 'classificationsIds',

        originalNameName: 'classificationsNames',

        setup() {
            this.setupTempFields();
            this.selectBoolFilterList = Classifications.prototype.selectBoolFilterList;
            this.boolFilterData = Classifications.prototype.boolFilterData;

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:' + this.idName, () => {
                let name = {}
                if (this.model.get(this.idName)) {
                    name[this.model.get(this.idName)] = this.model.get(this.nameName);
                }

                this.model.set(this.originalIdName, this.model.get(this.idName) ? [this.model.get(this.idName)] : []);
                this.model.set(this.originalNameName, name);
            });

            this.listenTo(this.model, 'change:' + this.originalIdName, this.setupTempFields);
        },

        setupTempFields: function () {
            const classificationId = this.model.get(this.originalIdName)?.at(-1);
            this.model.set(this.idName, classificationId ?? null, {silent: true});
            this.model.set(this.nameName, (this.model.get(this.originalNameName) ?? [])[classificationId] ?? null, {silent: true});
        },

        clearLink: function () {
            if (this.mode === 'search') {
                this.searchData.idValue = null;
                this.searchData.nameValue = null;
            }

            Dep.prototype.clearLink.call(this);
        },

        fetch: function () {
            const data = Dep.prototype.fetch.call(this);
            const ids = [];
            const names = {};

            if (data[this.idName]) {
                ids.push(data[this.idName]);
                names[data[this.idName]] = data[this.nameName]
            }

            data[this.originalIdName] = ids;
            data[this.originalNameName] = names;

            return data;
        },

        inlineEditSave: function () {
            Classifications.prototype.inlineEditSave.call(this);
        },

        createFilterView: function (rule, inputName, type, delay = true) {
            Classifications.prototype.createFilterView.call(this, rule, inputName, type, delay);
        },

        createQueryBuilderFilter(type = null) {
            return Classifications.prototype.createQueryBuilderFilter.call(this, type);
        }
    })
);
