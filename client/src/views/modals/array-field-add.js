

Espo.define('views/modals/array-field-add', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'add-modal',

        template: 'modals/array-field-add',

        data: function () {
            return {
                optionList: this.options.options,
                translatedOptions: this.options.translatedOptions
            };
        },

        events: {
            'click button[data-action="add"]': function (e) {
                var value = $(e.currentTarget).attr('data-value');
                this.trigger('add', value);
            },
        },

        setup: function () {

            this.header = this.translate('Add Item');

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

        },

    });
});

