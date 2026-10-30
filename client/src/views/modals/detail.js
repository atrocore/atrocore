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

Espo.define('views/modals/detail', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'detail-modal',

        header: false,

        template: 'modals/detail',

        editDisabled: false,

        fullFormDisabled: false,

        detailView: null,

        removeDisabled: true,

        columnCount: 2,

        backdrop: true,

        fitHeight: true,

        className: 'dialog dialog-record full-page-modal',

        rightSideView: 'views/record/right-side-view',

        sideDisabled: false,

        bottomDisabled: false,

        hasRightSideView: true,

        mode: 'detail',

        saveDisabled: false,

        setup: function () {

            var self = this;

            this.buttonList = [];

            if ('saveDisabled' in this.options) {
                this.saveDisabled = this.options.saveDisabled;
            }

            if ('editDisabled' in this.options) {
                this.editDisabled = this.options.editDisabled;
            }

            if ('removeDisabled' in this.options) {
                this.removeDisabled = this.options.removeDisabled;
            }

            if (this.model && this.getMetadata().get(['scopes', this.model.name, 'type']) === 'Archive') {
                this.editDisabled = true;
                this.removeDisabled = true;
            }

            this.fullFormDisabled = this.options.fullFormDisabled || this.fullFormDisabled;

            this.layoutName = this.options.layoutName || this.layoutName;

            if (!this.removeDisabled) {
                this.addRemoveButton();
            }

            if (!this.editDisabled) {
                this.addEditButton();
            }

            if (!this.saveDisabled) {
                this.buttonList.push({
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                });
            }

            this.buttonList.push({
                name: 'cancel',
                label: 'Cancel'
            });

            if (this.mode === 'detail' && this.model && this.model.collection && !this.navigateButtonsDisabled) {
                this.buttonList.push({
                    name: 'previous',
                    html: '<i class="ph ph-caret-left"></i>',
                    title: this.translate('Previous Entry'),
                    pullLeft: true,
                    className: 'btn-icon',
                    disabled: true
                });
                this.buttonList.push({
                    name: 'next',
                    html: '<i class="ph ph-caret-right"></i>',
                    title: this.translate('Next Entry'),
                    pullLeft: true,
                    className: 'btn-icon',
                    disabled: true
                });
                this.indexOfRecord = this.model.collection.indexOf(this.model);
            } else {
                this.navigateButtonsDisabled = true;
            }

            this.scope = this.scope || this.options.scope;
            this.id = this.options.id;

            this.waitForView('record');

            this.sourceModel = this.model;

            if (this.isSupertypeRecord()) {
                this.wait(true);
                this.switchToSubtypeRecord(() => {
                    this.setupModels();
                    this.wait(false);
                });
            } else {
                this.setupModels();
            }

            this.listenToOnce(this.getRouter(), 'routed', function () {
                this.remove();
            }, this);

            if (!this.model.isNew()) {
                this.listenTo(this, 'after:render', () => {
                    if (this.mode === 'edit') {
                        if ((this.options.htmlStatusIcons || []).length > 0) {
                            const iconsContainer = $('<div class="icons-container pull-right"></div>');
                            this.options.htmlStatusIcons.forEach(icon => iconsContainer.append(icon));
                            this.$el.find('.modal-body').prepend(iconsContainer);
                        }
                    }
                    this.applyOverviewFilters();
                });
            }

            this.listenTo(this.model, 'change', () => {
                if (this.dialog && this.dialog.$el) {
                    this.dialog.$el.trigger('shown.bs.modal')
                }
            });
        },

        isSupertypeRecord: function () {
            const scope = this.scope || this.options.scope;

            return !!this.options.id && !!this.getMetadata().get(['scopes', scope, 'hasSubtypes']);
        },

        /**
         * The record is shown with the layout of its subtype and saved via the subtype, the record of the list is
         * refreshed afterwards.
         */
        switchToSubtypeRecord: function (callback) {
            const scope = this.scope || this.options.scope;
            const listModel = this.sourceModel;
            const id = listModel ? listModel.id : this.options.id;

            Espo.Ajax.getRequest(`${scope}/${id}`, null, {metaContext: 'subtype'}).then(data => {
                const subtype = data && data._meta && data._meta.subtype;
                if (subtype && subtype.entityName) {
                    this.scope = subtype.entityName;
                    this.id = subtype.id;
                    this.sourceModel = null;

                    // the navigation goes through the records of the list
                    this.supertypeListModel = listModel || null;
                    if (listModel && listModel.collection) {
                        this.supertypeCollection = listModel.collection;
                    }

                    if (!this.supertypeListenersAdded) {
                        this.supertypeListenersAdded = true;
                        this.listenTo(this, 'after:save', () => {
                            if (this.supertypeListModel) {
                                this.supertypeListModel.fetch();
                            }
                        });
                        this.listenTo(this, 'after:destroy', () => {
                            const model = this.supertypeListModel;
                            if (model) {
                                model.trigger('destroy', model, model.collection);
                            }
                        });
                    }
                }

                callback(!!(subtype && subtype.entityName));
            });
        },

        switchToSupertypeRowByIndex: function (indexOfRecord) {
            const listModel = this.supertypeCollection.at(indexOfRecord);
            if (!listModel) {
                return;
            }

            this.scope = listModel.name;
            this.sourceModel = listModel;

            this.switchToSubtypeRecord(found => {
                if (!found) {
                    return;
                }

                this.indexOfRecord = indexOfRecord;

                this.getModelFactory().create(this.scope, model => {
                    this.model = model;
                    this.model.id = this.id;

                    this.listenToOnce(this.model, 'sync', () => {
                        this.createRecordView(() => {
                            // set element before reRender
                            this.setElement(this.containerSelector + ' .body');
                            this.reRender();
                            this.setupHeaderAndButtons();
                            this.$el.find('.modal-header .modal-title').html(this.header);
                        });

                        this.controlNavigationButtons();
                    });
                    this.model.fetch();
                });
            });
        },

        setupModels: function () {
            this.getModelFactory().create(this.scope, function (model) {
                if (!this.sourceModel) {
                    this.model = model;
                    this.model.id = this.id;

                    this.listenToOnce(this.model, 'sync', function () {
                        this.setupHeaderAndButtons()
                        this.createRecordView();
                    }, this);
                    this.model.fetch();
                } else {
                    this.model = this.sourceModel.clone();
                    this.model.collection = this.sourceModel.collection;
                    this.model.relationModel = this.sourceModel.relationModel;
                    this.listenTo(this.model, 'change', function () {
                        this.sourceModel.set(this.model.getClonedAttributes());
                    }, this);

                    this.once('after:render', function () {
                        this.model.fetch();
                        if (this.model.relationModel) {
                            this.model.relationModel.fetch()
                        }
                    }, this);
                    this.setupHeaderAndButtons()
                    this.createRecordView();
                }
            }, this);
        },

        addEditButton: function () {
            this.addButton({
                name: 'edit',
                label: 'Edit',
                style: 'primary'
            }, true);
        },


        addRemoveButton: function () {
            this.addButton({
                name: 'remove',
                label: 'Remove'
            }, true);
        },

        getScope: function () {
            return this.scope;
        },

        setupHeaderAndButtons: function () {
            var model = this.model;
            var scope = this.getScope();

            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);

            if (this.mode === 'detail') {
                if (this.getModelTitle(model)) {
                    this.header = Handlebars.Utils.escapeExpression(this.getModelTitle(model));
                } else {
                    this.header = this.getLanguage().translate(scope, 'scopeNames');
                }
            } else {
                if (!this.id) {
                    this.header = `${this.getLanguage().translate(this.scope, 'scopeNames')}: ${this.translate('New')}`;
                } else {
                    this.header = this.getLanguage().translate('Edit') + ': ' + this.getLanguage().translate(this.scope, 'scopeNames');
                }
            }

            this.header = iconHtml + this.header;

            if (!this.editDisabled) {
                var editAccess = this.getAcl().check(model, 'edit', true);
                if (editAccess === null) {
                    this.listenToOnce(model, 'sync', function () {
                        this.controlActionButtons()
                    }, this);
                }
            }

            if (!this.removeDisabled) {
                var removeAccess = this.getAcl().check(model, 'delete', true);
                if (removeAccess === null) {
                    this.listenToOnce(model, 'sync', function () {
                        this.controlActionButtons()
                    }, this);
                }
            }

            this.controlActionButtons()
        },

        controlActionButtons: function () {
            const hiddenButtons = []
            if (this.mode === 'edit') {
                hiddenButtons.push('edit', 'remove', 'next', 'previous');
            } else {
                hiddenButtons.push('save', 'cancel');
                if (!this.getAcl().check(this.model, 'edit', true)) {
                    hiddenButtons.push('edit');
                }
                if (!this.getAcl().check(this.model, 'delete', true)) {
                    hiddenButtons.push('delete');
                }
            }

            this.buttonList.forEach(button => {
                if (hiddenButtons.includes(button.name)) {
                    this.hideButton(button.name);
                } else {
                    this.showButton(button.name);
                }
            })
        },

        createRecordView: function (callback) {
            var model = this.model;

            var viewName =
                this.detailViewName ||
                this.detailView ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'detailSmall']) ||
                this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'detailQuick']) ||
                'views/record/detail-small';
            var options = {
                model: model,
                el: this.containerSelector + ' .record-container',
                layoutName: this.layoutName,
                layoutRelatedScope: this.options.layoutRelatedScope,
                columnCount: this.columnCount,
                buttonsDisabled: true,
                inlineEditDisabled: true,
                sideDisabled: this.sideDisabled,
                bottomDisabled: this.bottomDisabled,
                exit: function () {
                }
            };
            this.createView('record', viewName, options, callback);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.find('.modal-header .full-form').remove();

            if (!this.fullFormDisabled) {
                const fullFormButton = $('<a class="full-form" href="javascript:" data-action="fullForm"><i class="ph ph-frame-corners"></i></a>');
                fullFormButton.on('click', this.actionFullForm.bind(this));
                this.$el.find('.modal-header').prepend(fullFormButton)
            }

            setTimeout(function () {
                this.$el.children(0).scrollTop(0);
            }.bind(this), 50);

            if (!this.navigateButtonsDisabled) {
                this.controlNavigationButtons();
            }

            if ((this.options.htmlStatusIcons || []).length > 0) {
                const iconsContainer = $('<div class="icons-container pull-right"></div>');
                this.options.htmlStatusIcons.forEach(icon => iconsContainer.append(icon));
                this.$el.find('.modal-body').prepend(iconsContainer);
            }

            var recordView = this.getRecordView();
            const rightContainer = document.querySelector('#' + this.dialog.id + ' .modal-dialog .main-content .right-content');

            if (recordView?.sideView && rightContainer) {
                const props = recordView.getSvelteSideViewProps(this);
                this.destroySveltePanel()

                window['SvelteEntityContextPanel' + this.dialog.id] = new Svelte.EntityContextPanel({
                    target: rightContainer,
                    props: props
                });

                this.dialog.$el.on('hidden.bs.modal', (e) => {
                    this.destroySveltePanel();
                });
            }
        },

        destroySveltePanel: function () {
            if (window['SvelteEntityContextPanel' + this.dialog.id]) {
                try {
                    window['SvelteEntityContextPanel' + this.dialog.id].$destroy()
                } catch (e) {
                }
            }
        },

        getRecordView() {
            return this.getView('record')
        },


        controlNavigationButtons: function () {
            var recordView = this.getRecordView();
            if (!recordView) return;

            var indexOfRecord = this.indexOfRecord;

            var previousButtonEnabled = false;
            var nextButtonEnabled = false;

            if (indexOfRecord > 0) {
                previousButtonEnabled = true;
            }

            var collection = this.getNavigationCollection();

            if (indexOfRecord < collection.total - 1) {
                nextButtonEnabled = true;
            } else {
                if (collection.total === -1) {
                    nextButtonEnabled = true;
                } else if (collection.total === -2) {
                    if (indexOfRecord < collection.length - 1) {
                        nextButtonEnabled = true;
                    }
                }
            }

            if (previousButtonEnabled) {
                this.enableButton('previous');
            } else {
                this.disableButton('previous');
            }

            if (nextButtonEnabled) {
                this.enableButton('next');
            } else {
                this.disableButton('next');
            }
        },

        getNavigationCollection: function () {
            return this.supertypeCollection || this.model.collection;
        },

        switchToModelByIndex: function (indexOfRecord) {
            if (this.supertypeCollection) {
                this.switchToSupertypeRowByIndex(indexOfRecord);
                return;
            }

            if (!this.model.collection) return;

            this.sourceModel = this.model.collection.at(indexOfRecord);

            if (!this.sourceModel) {
                throw new Error("Model is not found in collection by index.");
            }

            this.indexOfRecord = indexOfRecord;

            this.id = this.sourceModel.id;
            this.scope = this.sourceModel.name;

            this.model = this.sourceModel.clone();
            this.model.collection = this.sourceModel.collection;

            this.listenTo(this.model, 'change', function () {
                this.sourceModel.set(this.model.getClonedAttributes());
            }, this);

            this.once('after:render', function () {
                this.model.fetch();
            }, this);

            this.createRecordView(function () {
                // set element before reRender
                this.setElement(this.containerSelector + ' .body');
                this.reRender();
                this.setupHeaderAndButtons()
                this.$el.find('.modal-header .modal-title').html(this.header);
            }.bind(this));

            this.controlNavigationButtons();
        },

        actionPrevious: function () {
            if (!this.getNavigationCollection()) return;
            if (!(this.indexOfRecord > 0)) return;

            var indexOfRecord = this.indexOfRecord - 1;
            this.switchToModelByIndex(indexOfRecord);
        },

        actionNext: function () {
            var collection = this.getNavigationCollection();
            if (!collection) return;
            if (!(this.indexOfRecord < collection.total - 1) && collection.total >= 0) return;
            if (collection.total === -2 && this.indexOfRecord >= collection.length - 1) {
                return;
            }

            var indexOfRecord = this.indexOfRecord + 1;
            if (indexOfRecord <= collection.length - 1) {
                this.switchToModelByIndex(indexOfRecord);
            } else {
                var initialCount = collection.length;

                this.listenToOnce(collection, 'sync', function () {
                    var model = collection.at(indexOfRecord);
                    this.switchToModelByIndex(indexOfRecord);
                }, this);
                collection.fetch({
                    more: true,
                    remove: false,
                });
            }
        },

        actionEdit: function () {
            this.setEditMode()
        },

        actionCancel: function () {
            this.getRecordView().cancelEdit()
            this.mode = 'detail'
            this.controlActionButtons()
        },

        setEditMode: function () {
            this.getRecordView().actionEdit();
            this.mode = 'edit'
            this.controlActionButtons();
        },

        actionSave: function () {
            let recordView = this.getRecordView();
            if (!recordView) {
                return;
            }

            var model = recordView.model;
            if (this.options.relate && this.options.relate.model) {
                model.defs['_relationName'] = this.options.relate.model.defs['_relationName'];
            }
            recordView.once('after:save', function () {
                this.trigger('after:save', model);
                this.dialog.close();
            }, this);

            var $buttons = this.dialog.$el.find('.modal-footer button');
            $buttons.addClass('disabled').attr('disabled', 'disabled');

            recordView.once('cancel:save', function () {
                $buttons.removeClass('disabled').removeAttr('disabled');
            }, this);

            recordView.save();
        },

        actionRemove: function () {
            var model = this.getRecordView().model;

            this.confirm(this.translate('removeRecordConfirmation', 'messages'), function () {
                var $buttons = this.dialog.$el.find('.modal-footer button');
                $buttons.addClass('disabled').attr('disabled', 'disabled');
                model.destroy({
                    success: function () {
                        this.trigger('after:destroy', model);
                        this.dialog.close();
                    }.bind(this),
                    error: function () {
                        $buttons.removeClass('disabled').removeAttr('disabled');
                    }
                });
            }, this);
        },

        actionFullForm: function () {
            var url;
            var router = this.getRouter();
            var attributes = this.getRecordView().fetch();
            var model = this.getRecordView().model;
            attributes = _.extend(attributes, model.getClonedAttributes());
            var options = {
                attributes: attributes,
                returnUrl: this.options.returnUrl || Backbone.history.fragment,
                returnDispatchParams: this.options.returnDispatchParams || null,
            };
            if (this.options.rootUrl) {
                options.rootUrl = this.options.rootUrl;
            }

            if (!this.id) {
                url = '#' + this.scope + '/create';
                options = { ...options, relate: this.options.relate }

                setTimeout(function () {
                    router.dispatch(this.scope, 'create', options);
                    router.navigate(url, { trigger: false });
                }.bind(this), 10);
            } else {
                if (this.mode === 'edit') {
                    url = '#' + this.scope + '/edit/' + this.id;

                    options = {
                        ...options,
                        model: this.sourceModel,
                        id: this.id
                    };

                    setTimeout(function () {
                        router.dispatch(this.scope, 'edit', options);
                        router.navigate(url, { trigger: false });
                    }.bind(this), 10);
                } else {
                    var scope = this.getScope();

                    url = '#' + scope + '/view/' + this.id;

                    options = {
                        ...options,
                        id: this.id
                    };

                    setTimeout(function () {
                        router.dispatch(scope, 'view', options);
                        router.navigate(url, { trigger: false });
                    }.bind(this), 10);
                }
            }

            this.trigger('leave');
            this.dialog.close();
        }
    });
});

