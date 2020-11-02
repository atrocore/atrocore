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

Espo.define('treo-core:views/admin/layouts/relationships', 'class-replace!treo-core:views/admin/layouts/relationships', function (Dep) {

    return Dep.extend({

        loadLayout: function (callback) {
            this.getModelFactory().create(this.scope, function (model) {
                this.getHelper().layoutManager.get(this.scope, this.type, function (layout) {
                    let allFields = [];
                    for (let field in model.defs.links) {
                        if (['hasMany', 'hasChildren'].indexOf(model.defs.links[field].type) !== -1) {
                            if (this.isLinkEnabled(model, field)) {
                                allFields.push(field);
                            }
                        }
                    }

                    let bottomPanels = this.getMetadata().get(['clientDefs', this.scope, 'bottomPanels', 'detail']) || [];
                    bottomPanels.forEach(panel => {
                        if (!panel.layoutRelationshipsDisabled) {
                            allFields.push(panel.name);
                        }
                    });

                    allFields.sort(function (v1, v2) {
                        let v1Name, v2Name;
                        let v1Options = bottomPanels.find(panel => panel.name === v1);
                        let v2Options = bottomPanels.find(panel => panel.name === v2);

                        if (v1 in model.defs.links) {
                            v1Name = this.translate(v1, 'links', this.scope);
                        } else if (v1Options) {
                            v1Name = this.translate(v1Options.label, 'labels', this.scope);
                        }

                        if (v2 in model.defs.links) {
                            v2Name = this.translate(v2, 'links', this.scope);
                        } else if (v2Options) {
                            v2Name = this.translate(v2Options.label, 'labels', this.scope);
                        }
                        return v1Name.localeCompare(v2Name);
                    }.bind(this));

                    this.enabledFieldsList = [];

                    this.enabledFields = [];
                    this.disabledFields = [];

                    for (let i in layout) {
                        let item = layout[i];
                        let o;
                        let options = bottomPanels.find(panel => panel.name === item.name);
                        if (typeof item === 'string' || item instanceof String) {
                            o = {
                                name: item,
                                label: options ? this.translate(options.label, 'labels', this.scope) : this.getLanguage().translate(item, 'links', this.scope)
                            };
                        } else {
                            o = item;
                            o.label = options ? this.translate(options.label, 'labels', this.scope) : this.getLanguage().translate(o.name, 'links', this.scope);

                        }
                        this.dataAttributeList.forEach(function (attribute) {
                            if (attribute === 'name') return;
                            if (attribute in o) return;

                            let value = this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', o.name, attribute]);
                            if (value === null) return;
                            o[attribute] = value;
                        }, this);

                        this.enabledFields.push(o);
                        this.enabledFieldsList.push(o.name);
                    }

                    for (let i in allFields) {
                        if (!_.contains(this.enabledFieldsList, allFields[i])) {
                            let options = bottomPanels.find(panel => panel.name === allFields[i]);
                            this.disabledFields.push({
                                name: allFields[i],
                                label: options ? this.translate(options.label, 'labels', this.scope): this.getLanguage().translate(allFields[i], 'links', this.scope)
                            });
                        }
                    }
                    this.rowLayout = this.enabledFields;

                    for (let i in this.rowLayout) {
                        let options = bottomPanels.find(panel => panel.name === this.rowLayout[i].name);
                        this.rowLayout[i].label = options ? this.getLanguage().translate(options.label, 'labels', this.scope)
                            : this.getLanguage().translate(this.rowLayout[i].name, 'links', this.scope);
                    }
                    callback();
                }.bind(this), false);
            }.bind(this));
        },

        validate: function () {
            return true;
        },

        isLinkEnabled: function (model, name) {
            return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
        }
    });
});

