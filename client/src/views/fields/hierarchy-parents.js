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

Espo.define('views/fields/hierarchy-parents', 'views/fields/link-multiple',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.linkMultiple = this.getMetadata().get(['scopes', this.model.urlRoot, 'multiParents']) || false;

            if (this.model.isNew()) {
                let fieldValueInheritance = this.getMetadata().get(['scopes', this.model.name, 'fieldValueInheritance']) || false;

                if (fieldValueInheritance) {
                    this.listenTo(this.model, 'change:parentsIds', () => {
                        let parentsIds = Espo.Utils.clone(this.model.get('parentsIds'));
                        if (!this.linkMultiple && parentsIds && parentsIds.length > 1) {
                            this.model.set('parentsIds', [parentsIds.pop()]);
                        }
                        this.loadParentData();
                    });
                }
            }
        },

        afterAddLink: function (id) {
            if (this.linkMultiple) return

            if (this.ids.length > 1) {
                // remove all previous elements
                const idsToDelete = this.ids.slice(0, -1)
                idsToDelete.forEach(id => {
                    this.deleteLink(id)
                })
            }
        },

        loadParentData() {
            setTimeout(() => {
                let parentsIds = Espo.Utils.clone(this.model.get('parentsIds'));
                if (!parentsIds || parentsIds.length !== 1) {
                    return;
                }

                let scope = this.model.urlRoot;

                let nonInheritedFields = ['parentsIds'];
                (this.getMetadata().get('app.nonInheritedFields') || []).forEach(field => {
                    this.pushFieldViaType(scope, field, nonInheritedFields);
                });
                (this.getMetadata().get(['scopes', scope, 'mandatoryUnInheritedFields']) || []).forEach(field => {
                    this.pushFieldViaType(scope, field, nonInheritedFields);
                });
                (this.getMetadata().get(['scopes', scope, 'unInheritedFields']) || []).forEach(field => {
                    this.pushFieldViaType(scope, field, nonInheritedFields);
                });

                this.ajaxPostRequest(`${scope}/action/getDuplicateAttributes`, {id: parentsIds.shift()}).then(data => {
                    $.each(data, (field, value) => {
                        if (!nonInheritedFields.includes(field) && !this.model.get(field)) {
                            this.model.set(field, value);
                        }
                    });
                });
            }, 300);
        },

        pushFieldViaType(scope, field, nonInheritedFields) {
            let type = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'type']);
            if (type === 'link') {
                nonInheritedFields.push(field + 'Id');
            } else if (type === 'linkMultiple') {
                nonInheritedFields.push(field + 'Ids');
            } else {
                nonInheritedFields.push(field);
            }
        },

    })
);