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

Espo.define('views/modals/edit', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'edit-modal',

        header: false,

        template: 'modals/edit',

        saveDisabled: false,

        fullFormDisabled: false,

        editView: null,

        columnCount: 2,

        escapeDisabled: true,

        fitHeight: true,

        className: 'dialog dialog-record',

        sideDisabled: false,

        bottomDisabled: false,

        setup: function () {

            var self = this;

            this.buttonList = [];

            if ('saveDisabled' in this.options) {
                this.saveDisabled = this.options.saveDisabled;
            }

            if (!this.saveDisabled) {
                this.buttonList.push({
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                });
            }

            this.fullFormDisabled = this.options.fullFormDisabled || this.fullFormDisabled;

            this.layoutName = this.options.layoutName || this.layoutName;

            if (!this.fullFormDisabled) {
                this.buttonList.push({
                    name: 'fullForm',
                    label: 'Full Form'
                });
            }

            this.buttonList.push({
                name: 'cancel',
                label: 'Cancel'
            });

            this.scope = this.scope || this.options.scope;
            this.id = this.options.id;

            this.sourceModel = this.model;

            this.waitForView('edit');

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

            this.getModelFactory().create(this.scope, function (model) {
                if (this.id) {
                    if (this.sourceModel) {
                        model = this.model = this.sourceModel.clone();
                    } else {
                        this.model = model;
                        model.id = this.id;
                    }
                    model.once('sync', function () {
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
                    if (this.options.relate) {
                        model.setRelate(this.options.relate);
                        model._relateData = this.options.relate;
                    }
                    if (this.options.attributes) {
                        model.set(this.options.attributes);
                    }
                    this.createRecordView(model);
                }
            }.bind(this));

            if (!this.id) {
                this.header = `${this.getLanguage().translate(this.scope, 'scopeNames')}: ${this.translate('New')}`;
            } else {
                this.header = this.getLanguage().translate('Edit') + ': ' + this.getLanguage().translate(this.scope, 'scopeNames');
            }

            if (!this.fullFormDisabled) {
                if (!this.id) {
                    this.header = '<a href="#' + this.scope + '/create" class="action" title="' + this.translate('Full Form') + '" data-action="fullForm">' + this.header + '</a>';
                } else {
                    this.header = '<a href="#' + this.scope + '/edit/' + this.id + '" class="action" title="' + this.translate('Full Form') + '" data-action="fullForm">' + this.header + '</a>';
                }
            }

            const iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);

            this.header = iconHtml + this.header;

            if (!this.model.isNew()) {
                this.listenTo(this, 'after:render', () => {
                    if ((this.options.htmlStatusIcons || []).length > 0) {
                        const iconsContainer = $('<div class="icons-container pull-right"></div>');
                        this.options.htmlStatusIcons.forEach(icon => iconsContainer.append(icon));
                        this.$el.find('.modal-body').prepend(iconsContainer);
                    }

                    this.applyOverviewFilters();
                });
            }
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy'
                && this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`) !== true ;
        },

        getNonInheritedFields: function () {
            let nonInheritedFields = this.getMetadata().get(`app.nonInheritedFields`) || [];

            (this.getMetadata().get(`scopes.${this.scope}.mandatoryUnInheritedFields`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${this.scope}.unInheritedFields`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`app.nonInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${this.scope}.mandatoryUnInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
            });

            (this.getMetadata().get(`scopes.${this.scope}.unInheritedRelations`) || []).forEach(field => {
                nonInheritedFields.push(field);
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
                el: this.containerSelector + ' .edit-container',
                layoutName: this.layoutName,
                layoutRelatedScope: this.options.layoutRelatedScope,
                columnCount: this.columnCount,
                buttonsDisabled: true,
                sideDisabled: this.sideDisabled,
                bottomDisabled: this.bottomDisabled,
                exit: function () {}
            };
            this.handleRecordViewOptions(options);
            this.createView('edit', viewName, options, callback);
        },

        handleRecordViewOptions: function (options) {},

        actionSave: function () {
            let editView = this.getView('edit');
            if (!editView) {
                return;
            }

            var model = editView.model;
            if (this.options.relate && this.options.relate.model) {
                model.defs['_relationName'] = this.options.relate.model.defs['_relationName'];
            }
            editView.once('after:save', function () {
                this.trigger('after:save', model);
                this.dialog.close();
            }, this);

            var $buttons = this.dialog.$el.find('.modal-footer button');
            $buttons.addClass('disabled').attr('disabled', 'disabled');

            editView.once('cancel:save', function () {
                $buttons.removeClass('disabled').removeAttr('disabled');
            }, this);

            editView.save();
        },

        actionFullForm: function (dialog) {
            var url;
            var router = this.getRouter();
            if (!this.id) {
                url = '#' + this.scope + '/create';

                var attributes = this.getView('edit').fetch();
                var model = this.getView('edit').model;
                attributes = _.extend(attributes, model.getClonedAttributes());

                var options = {
                    attributes: attributes,
                    relate: this.options.relate,
                    returnUrl: this.options.returnUrl || Backbone.history.fragment,
                    returnDispatchParams: this.options.returnDispatchParams || null,
                };
                if (this.options.rootUrl) {
                    options.rootUrl = this.options.rootUrl;
                }

                setTimeout(function () {
                    router.dispatch(this.scope, 'create', options);
                    router.navigate(url, {trigger: false});
                }.bind(this), 10);
            } else {
                url = '#' + this.scope + '/edit/' + this.id;

                var attributes = this.getView('edit').fetch();
                var model = this.getView('edit').model;
                attributes = _.extend(attributes, model.getClonedAttributes());

                var options = {
                    attributes: attributes,
                    returnUrl: this.options.returnUrl || Backbone.history.fragment,
                    returnDispatchParams: this.options.returnDispatchParams || null,
                    model: this.sourceModel,
                    id: this.id
                };
                if (this.options.rootUrl) {
                    options.rootUrl = this.options.rootUrl;
                }

                setTimeout(function () {
                    router.dispatch(this.scope, 'edit', options);
                    router.navigate(url, {trigger: false});
                }.bind(this), 10);
            }

            this.trigger('leave');
            this.dialog.close();
        }
    });
});

