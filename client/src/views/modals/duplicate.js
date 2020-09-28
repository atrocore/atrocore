

Espo.define('views/modals/duplicate', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'duplicate-modal',

        header: false,

        template: 'modals/duplicate',

        data: function () {
            return {
                scope: this.scope,
                duplicates: this.duplicates
            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'danger',
                    onClick: function (dialog) {
                        this.trigger('save');
                        dialog.close();
                    }.bind(this),
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
            this.scope = this.options.scope;
            this.duplicates = this.options.duplicates;
        },

    });
});

