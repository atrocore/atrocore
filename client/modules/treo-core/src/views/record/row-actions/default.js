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

Espo.define('treo-core:views/record/row-actions/default', 'class-replace!treo-core:views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/row-actions/default',

        events: {
            'click .action': function (e) {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] === 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            }
        },

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            let rowActions = this.getMetadata().get(['clientDefs', this.model.name, 'additionalRowActions']) || {};
            Object.keys(rowActions).forEach((item) => {
                let action = {
                    action: rowActions[item].action,
                    label: rowActions[item].label
                };
                if (rowActions[item].iconClass) {
                    let htmlLogo = `<span class="additional-action-icon ${rowActions[item].iconClass}"></span>`;
                    action.html = `${this.translate(rowActions[item].label, 'labels', this.model.name)} ${htmlLogo}`;
                }
                list.push(action);

                let method = 'action' + Espo.Utils.upperCaseFirst(rowActions[item].action);
                this[method] = function () {
                    let path = rowActions[item].actionViewPath;

                    let o = {};
                    (rowActions[item].optionsToPass || []).forEach((option) => {
                        if (option in this) {
                            o[option] = this[option];
                        }
                    });

                    this.createView(item, path, o, (view) => {
                        if (typeof view[rowActions[item].action] === 'function') {
                            view[rowActions[item].action]();
                        }
                    });
                };
            }, this);

            return list;
        },

    });
});