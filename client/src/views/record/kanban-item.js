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

Espo.define('views/record/kanban-item', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/kanban-item',

        data: function () {
            return {
                layoutDataList: this.layoutDataList,
                rowActionsDisabled: this.rowActionsDisabled,
                assignedUserId: this.model.get('assignedUserId'),
                assignedUserName: this.model.get('assignedUserName'),
            };
        },

        events: {

        },

        setup: function () {
            this.itemLayout = this.options.itemLayout;
            this.rowActionsView = this.options.rowActionsView;
            this.rowActionsDisabled = this.options.rowActionsDisabled;

            this.layoutDataList = [];

            this.itemLayout.forEach(function (item, i) {
                var name = item.name;
                var key = name + 'Field';
                var o = {
                    name: name,
                    isAlignRight: item.align === 'right',
                    isLarge: item.isLarge,
                    isFirst: i === 0,
                    cssStyle: item.cssStyle ? item.cssStyle : '',
                    key: key
                };

                var viewName = item.view || this.model.getFieldParam(name, 'view');

                if(this.model.getFieldType(name) === 'bool'){
                    o.showLabel = true;
                    o.label = this.translate(name, 'fields', this.model.name)
                }

                if (!viewName) {
                    var type = this.model.getFieldType(name) || 'base';
                    viewName = this.getFieldManager().getViewName(type);
                }

                var mode = 'list';
                if (item.link) {
                    mode = 'listLink';
                }

                let valueEmpty = !(this.model.get(name));

                if (!valueEmpty && Array.isArray(this.model.get(name)) && this.model.get(name).length === 0) {
                    valueEmpty = true;
                }

                if (this.model.get(name + 'Id')){
                    valueEmpty = false;
                }

                if (this.model.get(name + 'Ids')){
                    valueEmpty = false;
                }

                if (!valueEmpty) {
                    this.createView(key, viewName, {
                        model: this.model,
                        name: name,
                        mode: mode,
                        readOnly: true,
                        isKanban: true,
                        el: this.getSelector() + ' .field[data-name="' + name + '"]'
                    });
                    this.layoutDataList.push(o);
                }

            }, this);

            if (!this.rowActionsDisabled) {
                var acl =  {
                    edit: this.getAcl().checkModel(this.model, 'edit'),
                    delete: this.getAcl().checkModel(this.model, 'delete')
                };
                this.createView('itemMenu', this.rowActionsView, {
                    el: this.getSelector() + ' .item-menu-container',
                    model: this.model,
                    acl: acl,
                    statusFieldIsEditable: this.options.statusFieldIsEditable
                });
            }
        }

    });
});
