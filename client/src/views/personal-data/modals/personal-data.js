

Espo.define('views/personal-data/modals/personal-data', ['views/modal'], function (Dep) {

    return Dep.extend({

        className: 'dialog dialog-record',

        template: 'personal-data/modals/personal-data',

        backdrop: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            this.header = this.getLanguage().translate('Personal Data');
            this.header += ': ' + Handlebars.Utils.escapeExpression(this.model.get('name'));

            if (this.getAcl().check(this.model, 'edit')) {
                this.buttonList.unshift({
                    name: 'erase',
                    label: 'Erase',
                    style: 'danger',
                    disabled: true
                });
            }

            this.fieldList = [];

            this.scope = this.model.name;

            this.createView('record', 'views/personal-data/record/record', {
                el: this.getSelector() + ' .record',
                model: this.model
            }, function (view) {
                this.listenTo(view, 'check', function (fieldList) {
                    this.fieldList = fieldList;
                    if (fieldList.length) {
                        this.enableButton('erase');
                    } else {
                        this.disableButton('erase');
                    }
                });

                if (!view.fieldList.length) {
                    this.disableButton('export');
                }
            });
        },

        actionErase: function () {
            this.confirm({
                message: this.translate('erasePersonalDataConfirmation', 'messages'),
                confirmText: this.translate('Erase')
            }, function () {
                this.disableButton('erase');
                this.ajaxPostRequest('DataPrivacy/action/erase', {
                    fieldList: this.fieldList,
                    entityType: this.scope,
                    id: this.model.id
                }).then(function () {
                    Espo.Ui.success(this.translate('Done'));

                    this.trigger('erase');
                }.bind(this)).fail(function () {
                    this.enableButton('erase');
                }.bind(this));
            }.bind(this));
        }

    });
});
