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

Espo.define('treo-core:views/fields/link-parent', 'class-replace!treo-core:views/fields/link-parent', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.nameName = this.name + 'Name';
            this.typeName = this.name + 'Type';
            this.idName = this.name + 'Id';

            this.foreignScopeList = this.options.foreignScopeList || this.foreignScopeList;
            this.foreignScopeList = this.foreignScopeList || this.params.entityList || this.model.getLinkParam(this.name, 'entityList') || [];
            this.foreignScopeList = Espo.Utils.clone(this.foreignScopeList).filter(function (item) {
                if (!this.getMetadata().get(['scopes', item, 'disabled'])) return true;
            }, this);

            this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];

            this.listenTo(this.model, 'change:' + this.typeName, function () {
                this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];
            }.bind(this));

            if ('createDisabled' in this.options) {
                this.createDisabled = this.options.createDisabled;
            }

            var self = this;

            if (this.mode != 'list') {
                this.addActionHandler('selectLink', function () {
                    this.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.getMandatorySelectAttributeList(),
                        forceSelectAllAttributes: this.isForceSelectAllAttributes()
                    }, function (dialog) {
                        dialog.render();
                        Espo.Ui.notify(false);
                        this.listenToOnce(dialog, 'select', function (model) {
                            this.clearView('dialog');
                            this.select(model);
                        }, this);
                    }, this);
                });
                this.addActionHandler('clearLink', function () {
                    this.$elementName.val('');
                    this.$elementId.val('');
                    this.trigger('change');
                });

                this.events['change select[name="' + this.typeName + '"]'] = function (e) {
                    this.foreignScope = e.currentTarget.value;
                    this.$elementName.val('');
                    this.$elementId.val('');
                }
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if(this.mode !== 'list' && !this.foreignScopeList.length) {
                this.hide();
            }
        },

    });
});
