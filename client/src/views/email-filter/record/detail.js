

Espo.define('views/email-filter/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupFilterFields();
        },

        setupFilterFields: function () {
            this.controlIsGlobal();
            this.listenTo(this.model, 'change:isGlobal', function (model, value, data) {
                this.controlIsGlobal();
                if (data.ui) {
                    if (model.get('isGlobal')) {
                        this.model.set({
                            parentId: null,
                            parentType: null,
                            parentName: null
                        });
                    } else {
                        this.model.set('parentType', 'User');
                        this.model.set('parentId', this.getUser().id);
                        this.model.set('parentName', this.getUser().get('name'));
                    }
                }
            }, this);

            if (!this.getUser().isAdmin()) {
                this.setFieldReadOnly('parent');
                this.setFieldReadOnly('isGlobal');
            }

            if (this.model.isNew()) {
                if (!this.model.get('parentId')) {
                    this.model.set('parentType', 'User');
                    this.model.set('parentId', this.getUser().id);
                    this.model.set('parentName', this.getUser().get('name'));
                }
                if (!this.getUser().isAdmin()) {
                    this.hideField('isGlobal');
                }

                this.setFieldRequired('parent');
            } else {
                this.setFieldReadOnly('isGlobal');
                this.setFieldReadOnly('parent');
            }


            this.controlEmailFolder();
            this.listenTo(this.model, 'change', function () {
                this.controlEmailFolder();
            }, this);
        },

        controlIsGlobal: function () {
            if (this.model.get('isGlobal')) {
                this.hideField('parent');
            } else {
                this.showField('parent');
            }
        },

        controlEmailFolder: function () {
            if (this.model.get('action') !== 'Move to Folder' || this.model.get('parentType') !== 'User') {
                this.hideField('emailFolder');
            } else {
                this.showField('emailFolder');
            }
        }

    });

});

