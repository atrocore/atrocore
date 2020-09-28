

Espo.define('views/admin/entity-manager/modals/edit-formula', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        _template: '<div class="record">{{{record}}}</div>',

        data: function () {
            return {
            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            var scope = this.scope = this.options.scope || false;

            this.header = this.translate('Formula', 'labels', 'EntityManager') + ': ' + this.translate(scope, 'scopeNames');

            var model = this.model = new Model();
            model.name = 'EntityManager';

            this.wait(true);
            this.ajaxGetRequest('Metadata/action/get', {
                key: 'formula.' + scope
            }).then(function (formulaData) {
                formulaData = formulaData || {};

                model.set('beforeSaveCustomScript', formulaData.beforeSaveCustomScript || null);

                this.createView('record', 'views/admin/entity-manager/record/edit-formula', {
                    el: this.getSelector() + ' .record',
                    model: model,
                    targetEntityType: this.scope
                });
                this.wait(false);
            }.bind(this));
        },

        actionSave: function () {
            this.disableButton('save');

            var data = this.getView('record').fetch();
            this.model.set(data);
            if (this.getView('record').validate()) return;

            if (data.beforeSaveCustomScript === '') {
                data.beforeSaveCustomScript = null;
            }

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('EntityManager/action/formula', {
                data: data,
                scope: this.scope
            }).then(function () {
                Espo.Ui.success(this.translate('Saved'));
                this.trigger('after:save');
            }.bind(this)).fail(function () {
                this.enableButton('save');
            }.bind(this));
        }


    });
});

