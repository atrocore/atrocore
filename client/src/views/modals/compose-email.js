

Espo.define('views/modals/compose-email', 'views/modals/edit', function (Dep) {

    return Dep.extend({

        scope: 'Email',

        layoutName: 'composeSmall',

        saveDisabled: true,

        fullFormDisabled: true,

        columnCount: 2,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList.unshift({
                name: 'saveDraft',
                text: this.translate('Save Draft', 'labels', 'Email'),
            });

            this.buttonList.unshift({
                name: 'send',
                text: this.translate('Send', 'labels', 'Email'),
                style: 'primary'
            });

            this.header = this.getLanguage().translate('Compose Email');

            if (this.getPreferences().get('emailUseExternalClient') || !this.getAcl().checkScope('Email', 'create')) {
                var attributes = this.options.attributes || {};

                require('email-helper', function (EmailHelper) {
                    var emailHelper = new EmailHelper();
                    var link = emailHelper.composeMailToLink(attributes, this.getConfig().get('outboundEmailBccAddress'));
                    document.location.href = link;
                }.bind(this));

                this.once('after:render', function () {
                    this.actionClose();
                }, this);
                return;
            }
        },

        createRecordView: function (model, callback) {
            var viewName = this.getMetadata().get('clientDefs.' + model.name + '.recordViews.compose') || 'views/email/record/compose';
            var options = {
                model: model,
                el: this.containerSelector + ' .edit-container',
                type: 'editSmall',
                layoutName: this.layoutName || 'detailSmall',
                columnCount: this.columnCount,
                buttonsDisabled: true,
                selectTemplateDisabled: this.options.selectTemplateDisabled,
                removeAttachmentsOnSelectTemplate: this.options.removeAttachmentsOnSelectTemplate,
                signatureDisabled: this.options.signatureDisabled,
                exit: function () {}
            };
            this.createView('edit', viewName, options, callback);
        },

        actionSend: function () {
            var dialog = this.dialog;

            var editView = this.getView('edit');

            var model = editView.model;

            var afterSend = function () {
                this.trigger('after:save', model);
                this.trigger('after:send', model);
                dialog.close();
            };

            editView.once('after:send', afterSend, this);

            this.disableButton('send');
            this.disableButton('saveDraft');

            editView.once('cancel:save', function () {
                this.enableButton('send');
                this.enableButton('saveDraft');

                editView.off('after:save', afterSend);
            }, this);

            editView.send();
        },

        actionSaveDraft: function () {
            var dialog = this.dialog;

            var editView = this.getView('edit');

            var model = editView.model;

            this.disableButton('send');
            this.disableButton('saveDraft');

            var afterSave = function () {
                this.enableButton('send');
                this.enableButton('saveDraft');
                Espo.Ui.success(this.translate('savedAsDraft', 'messages', 'Email'));
            }.bind(this);

            editView.once('after:save', afterSave , this);

            editView.once('cancel:save', function () {
                this.enableButton('send');
                this.enableButton('saveDraft');

                editView.off('after:save', afterSave);
            }, this);

            editView.saveDraft();
        }

    });
});

