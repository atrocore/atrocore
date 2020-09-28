

Espo.define('views/email/record/edit', ['views/record/edit', 'views/email/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        init: function () {
            Dep.prototype.init.call(this);
            Detail.prototype.layoutNameConfigure.call(this);
        },

        handleAttachmentField: function () {
            if ((this.model.get('attachmentsIds') || []).length == 0 && !this.isNew) {
                this.hideField('attachments');
            } else {
                this.showField('attachments');
            }
        },

        handleCcField: function () {
            if (!this.model.get('cc')) {
                this.hideField('cc');
            } else {
                this.showField('cc');
            }
        },

        handleBccField: function () {
            if (!this.model.get('bcc')) {
                this.hideField('bcc');
            } else {
                this.showField('bcc');
            }
        },

        afterRender: function () {
        	Dep.prototype.afterRender.call(this);

            if (this.model.get('status') === 'Draft') {
                this.setFieldReadOnly('dateSent');
            }

            this.handleAttachmentField();
            this.listenTo(this.model, 'change:attachmentsIds', function () {
                this.handleAttachmentField();
            }, this);
            this.handleCcField();
            this.listenTo(this.model, 'change:cc', function () {
                this.handleCcField();
            }, this);
            this.handleBccField();
            this.listenTo(this.model, 'change:bcc', function () {
                this.handleBccField();
            }, this);
        },

    });
});

