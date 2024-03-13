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

Espo.define('views/fields/enum-column', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        searchTypeList: ['anyOf', 'noneOf'],

        fetchSearch: function () {
            var type = this.$el.find('[name="'+this.name+'-type"]').val();

            var list = this.$element.val().split(':,:');
            if (list.length === 1 && list[0] == '') {
                list = [];
            }

            list.forEach(function (item, i) {
                list[i] = this.parseItemForSearch(item);
            }, this);

            if (type === 'anyOf') {
                if (list.length === 0) {
                    return {
                        data: {
                            type: 'anyOf',
                            valueList: list
                        }
                    };
                }
                return {
                    type: 'columnIn',
                    value: list,
                    data: {
                        type: 'anyOf',
                        valueList: list
                    }
                };
            } else if (type === 'noneOf') {
                if (list.length === 0) {
                    return {
                        data: {
                            type: 'noneOf',
                            valueList: list
                        }
                    };
                }
                return {
                    type: 'or',
                    value: [
                        {
                            type: 'columnIsNull',
                            attribute: this.name
                        },
                        {
                            type: 'columnNotIn',
                            value: list,
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: 'noneOf',
                        valueList: list
                    }
                };
            }
        }

    });
});

