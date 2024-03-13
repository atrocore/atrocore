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

Espo.define('views/admin/field-manager/fields/link/extensible-enum-default', 'views/fields/link', Dep => {

    return Dep.extend({

        selectBoolFilterList: ['defaultOption'],

        createDisabled: true,

        boolFilterData: {
            defaultOption() {
                let extensibleEnumId = null;
                if (this.model.defs.fields[this.name] && this.model.defs.fields[this.name].extensibleEnumId) {
                    extensibleEnumId = this.model.defs.fields[this.name].extensibleEnumId;
                } else if (this.getParentView().getView('extensibleEnum')) {
                    extensibleEnumId = this.getParentView().getView('extensibleEnum').fetch().extensibleEnumId
                }
                return {
                    extensibleEnumId: extensibleEnumId
                };
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.foreignScope = 'ExtensibleEnumOption';

            this.listenTo(this.model, 'change:extensibleEnumId', () => {
                this.model.set('defaultId', null);
                this.model.set('defaultName', null);
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('defaultId')) {
                this.ajaxGetRequest(`${this.foreignScope}/${this.model.get('defaultId')}`).success(record => {
                    if (this.model.get('defaultName') !== record.name) {
                        this.model.set('defaultName', record.name);
                    }
                });
            }
        },

    });
});