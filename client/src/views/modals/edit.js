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

Espo.define('views/modals/edit', 'views/modals/detail', function (Dep) {

    return Dep.extend({
        cssName: 'edit-modal',

        editView: null,

        escapeDisabled: true,

        mode: 'edit',

        setup: function () {
            this.scope = this.scope || this.options.scope;

            // a record of an entity with subtypes is created via one of its subtypes
            if (!this.options.id && this.getMetadata().get(['scopes', this.scope, 'hasSubtypes'])) {
                this.wait(true);
                this.selectSubtype(subtype => {
                    this.scope = subtype;
                    this.setupForScope();
                    this.wait(false);
                });
                return;
            }

            this.setupForScope();
        },

        setupForScope: function () {
            if (this.options.relate) {
                this.relationScope = Espo.utils.upperCaseFirst(this.getMetadata().get(['entityDefs', this.scope, 'links', this.options.relate.link, 'relationName']))
            }

            Dep.prototype.setup.call(this)
        },

        selectSubtype: function (callback) {
            let selected = false;

            this.createView('selectSubtype', 'views/modals/select-subtype', {scope: this.scope}, view => {
                view.render();

                this.listenToOnce(view, 'select', subtype => {
                    selected = true;
                    view.close();
                    callback(subtype);
                });

                this.listenToOnce(view, 'remove', () => {
                    if (!selected) {
                        this.remove();
                    }
                });
            });
        },

        /**
         * A subtype record is related to other records via its supertype record.
         */
        prepareRelate: function (relate) {
            const model = relate.model;
            const supertype = model && typeof model.getSupertype === 'function' ? model.getSupertype() : null;
            if (!supertype || !model.get('supertypeId')) {
                return relate;
            }

            if (this.getMetadata().get(['entityDefs', this.scope, 'links', relate.link, 'entity']) !== supertype) {
                return relate;
            }

            const supertypeModel = model.clone();
            supertypeModel.id = model.get('supertypeId');
            supertypeModel.name = supertype;

            return Object.assign({}, relate, {model: supertypeModel});
        },

        setupModels: function () {
            let preparedNonInheritedFields = [];
            this.getNonInheritedFields().forEach(field => {
                if (this.getMetadata().get(`entityDefs.${this.scope}.fields.${field}.type`) === 'link') {
                    preparedNonInheritedFields.push(field + 'Id');
                    preparedNonInheritedFields.push(field + 'Name');
                } else if (this.getMetadata().get(`entityDefs.${this.scope}.fields.${field}.type`) === 'linkMultiple') {
                    preparedNonInheritedFields.push(field + 'Ids');
                } else {
                    preparedNonInheritedFields.push(field);
                }
            });

            this.getModels((model, relationModel) => {
                if (this.id) {
                    if (this.sourceModel) {
                        model = this.model = this.sourceModel.clone();
                        if (this.sourceModel.relationModel) {
                            model.relationModel = relationModel = this.sourceModel.relationModel.clone()
                            relationModel.fetch()
                        }
                    } else {
                        this.model = model;
                        model.id = this.id;
                    }
                    model.once('sync', function () {
                        this.setupHeaderAndButtons()
                        this.createRecordView(model);
                    }, this);
                    model.fetch();
                } else {
                    if (
                        this.isHierarchical()
                        && this.getMetadata().get(`scopes.${this.scope}.fieldValueInheritance`) === true
                        && this.options.relate
                        && this.options.relate.model
                        && this.options.relate.model.attributes
                    ) {
                        $.each(this.options.relate.model.attributes, (field, value) => {
                            if (!preparedNonInheritedFields.includes(field)) {
                                model.set(field, value);
                            }
                        });
                    }
                    this.model = model;
                    model.relationModel = relationModel
                    if (this.options.relate) {
                        this.options.relate.nameValueCallback = (model) => this.getLocalizedFieldValue(model, model.nameField)
                        model.setRelate(this.prepareRelate(this.options.relate));
                        model._relateData = this.options.relate;
                    }
                    if (this.options.attributes) {
                        model.set(this.options.attributes);
                    }
                    this.setupHeaderAndButtons()
                    this.createRecordView(model);
                }
            })
        },

        getModels(callback) {
            this.getModelFactory().create(this.scope, model => {
                if (!this.relationScope) {
                    callback(model, null)
                } else {
                    this.getModelFactory().create(this.relationScope, function (relationModel) {
                        callback(model, relationModel)
                    })
                }
            })
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy';
        },

        getNonInheritedFields: function () {
            let nonInheritedFields = this.getMetadata().get(`app.nonInheritedFields`) || [];

            (this.getMetadata().get(`scopes.${this.scope}.mandatoryUnInheritedFields`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            $.each((this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}), (field, fieldDefs) => {
                if (fieldDefs.inheritanceDisabled) {
                    nonInheritedFields.push(field);
                }
            });

            $.each((this.getMetadata().get(`entityDefs.${this.scope}.links`) || {}), (link, linkDefs) => {
                if (linkDefs.type && linkDefs.type === 'hasMany') {
                    if (!linkDefs.relationName) {
                        nonInheritedFields.push(link);
                    }
                }
            });

            return nonInheritedFields;
        },

        createRecordView: function (model, callback) {
            var viewName =
                this.editViewName ||
                this.editView ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editSmall']) ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editQuick']) ||
                'views/record/edit-small';
            var options = {
                model: model,
                el: this.containerSelector + ' .record-container',
                layoutName: this.layoutName,
                layoutRelatedScope: this.options.layoutRelatedScope,
                columnCount: this.columnCount,
                buttonsDisabled: true,
                sideDisabled: this.sideDisabled,
                bottomDisabled: this.bottomDisabled,
                exit: function () {
                }
            };
            this.handleRecordViewOptions(options);
            this.createView('record', viewName, options, callback);
        },

        handleRecordViewOptions: function (options) {
        },

        actionCancel: function () {
            Dep.prototype.actionClose.call(this);
        },
    });
});

