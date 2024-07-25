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

Espo.define('views/fields/measure', ['views/fields/extensible-enum', 'views/fields/link'], (Dep, Link) => {

    return Dep.extend({

        selectBoolFilterList: ['fromMeasure'],

        boolFilterData: {
            fromMeasure() {
                return {
                    measureId: this.getMeasureId()
                };
            }
        },

        setup: function () {
            this.idName = this.name;
            this.nameName = this.name + 'Name';
            this.foreignScope = 'Unit';

            Link.prototype.setup.call(this);
        },

        getMeasureId() {
            let measureId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'measureId']);
            if (this.params.measureId) {
                measureId = this.params.measureId;
            }

            return measureId;
        },

        getOptionsData() {
            return {}
        },

        fetchSearch() {
            var type = this.$el.find('[name="' + this.name + '-type"]').val();

            if (type === 'isEmpty') {
                return {
                    type: 'or',
                    value: [
                        {
                            type: 'isNull',
                            attribute: this.name
                        },
                        {
                            type: 'equals',
                            value: '',
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: type
                    }
                };
            }

            if (type === 'isNotEmpty') {
                return {
                    type: 'and',
                    value: [
                        {
                            type: 'isNotNull',
                            attribute: this.name
                        },
                        {
                            type: 'notEquals',
                            value: '',
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: type
                    }
                };
            }

            return Dep.prototype.fetchSearch.call(this)
        }
    });
});

