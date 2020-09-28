

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

            if (!this.id) {
                this.header = this.getLanguage().translate('Create ' + this.scope, 'labels', this.scope);
            } else {
                this.header = this.getLanguage().translate('Edit');
                this.header += ': ' + this.getLanguage().translate(this.scope, 'scopeNames');
            }

            if (!this.fullFormDisabled) {
                if (!this.id) {
                    this.header = '<a href="#' + this.scope + '/create" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                } else {
                    this.header = '<a href="#' + this.scope + '/edit/' + this.id+'" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                }
            }

            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header = iconHtml + this.header;

            this.sourceModel = this.model;

            this.waitForView('edit');

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
                    this.model = model;
                    if (this.options.relate) {
                        model.setRelate(this.options.relate);
                    }
                    if (this.options.attributes) {
                        model.set(this.options.attributes);
                    }
                    this.createRecordView(model);
                }
            }.bind(this));
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
                type: 'editSmall',
                layoutName: this.layoutName || 'detailSmall',
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
            var editView = this.getView('edit');

            var model = editView.model;
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

