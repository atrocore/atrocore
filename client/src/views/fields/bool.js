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

Espo.define('views/fields/bool', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'bool',

        listTemplate: 'fields/bool/list',

        detailTemplate: 'fields/bool/detail',

        editTemplate: 'fields/bool/edit',

        searchTemplate: 'fields/bool/search',

        validations: [],

        defaultFilterValue: false,

        notNull: true,

        setup(){
            this.notNull = this.params?.notNull
                ?? this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'notNull']) ?? true;
            this.params.options = ['','false','true'];
            this.translatedOptions = {
                '':'NULL',
                'false': this.translate('No'),
                'true': this.translate('Yes'),
            }
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.valueIsSet = this.model.has(this.name);
            data.notNull = this.notNull
            data.isNull = !this.model.get(this.name);
            data.translatedOptions = this.translatedOptions
            return data;
        },

        fetch: function () {
            let value = null;
            if(this.notNull){
                this.$el.find('input[name=' + this.name + ']').is(":checked");
            }else{
                let val = this.$el.find('[name="' + this.name + '"]').val();
                value = val ? val==="true" : null;
            }
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
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'boolean',
                operators: [
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});

