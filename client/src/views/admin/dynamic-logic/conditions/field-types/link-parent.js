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

Espo.define('views/admin/dynamic-logic/conditions/field-types/link-parent', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            var valueView = this.getView('value');

            var item;

            if (valueView) {
                valueView.fetchToModel();
            }

            if (this.type === 'equals' || this.type === 'notEquals') {
                var values = {};
                values[this.field + 'Id'] = valueView.model.get(this.field + 'Id');
                values[this.field + 'Name'] = valueView.model.get(this.field + 'Name');
                values[this.field + 'Type'] = valueView.model.get(this.field + 'Type');

                if (this.type === 'equals') {
                    item = {
                        type: 'and',
                        value: [
                            {
                                type: 'equals',
                                attribute: this.field + 'Id',
                                value: valueView.model.get(this.field + 'Id')
                            },
                            {
                                type: 'equals',
                                attribute: this.field + 'Type',
                                value: valueView.model.get(this.field + 'Type')
                            }
                        ],
                        data: {
                            field: this.field,
                            type: 'equals',
                            values: values
                        }
                    };
                } else {
                    item = {
                        type: 'or',
                        value: [
                            {
                                type: 'notEquals',
                                attribute: this.field + 'Id',
                                value: valueView.model.get(this.field + 'Id')
                            },
                            {
                                type: 'notEquals',
                                attribute: this.field + 'Type',
                                value: valueView.model.get(this.field + 'Type')
                            }
                        ],
                        data: {
                            field: this.field,
                            type: 'notEquals',
                            values: values
                        }
                    };
                }
            } else {
                item = {
                    type: this.type,
                    attribute: this.field + 'Id',
                    data: {
                        field: this.field
                    }
                };
            }

            return item;
        }

    });

});

