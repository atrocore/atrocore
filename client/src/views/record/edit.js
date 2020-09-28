

Espo.define('views/record/edit', 'views/record/detail', function (Dep) {

    return Dep.extend({

        template: 'record/edit',

        type: 'edit',

        name: 'edit',

        fieldsMode: 'edit',

        mode: 'edit',

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel',
            }
        ],

        dropdownItemList: [],

        sideView: 'views/record/edit-side',

        bottomView: 'views/record/edit-bottom',

        duplicateAction: false,

        actionSave: function () {
            this.save();
        },

        actionCancel: function () {
            this.cancel();
        },

        cancel: function () {
            if (this.isChanged) {
                this.model.set(this.attributes);
            }
            this.setIsNotChanged();
            this.exit('cancel');
        },

        setupBeforeFinal: function () {
            if (this.model.isNew()) {
                this.populateDefaults();
            }
            Dep.prototype.setupBeforeFinal.call(this);
        }

    });
});


