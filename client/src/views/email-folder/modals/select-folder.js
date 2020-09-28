


Espo.define('views/email-folder/modals/select-folder', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'select-folder',

        template: 'email-folder/modals/select-folder',

        fitHeight: true,

        data: function () {
            return {
                dashletList: this.dashletList,
            };
        },

        events: {
            'click a[data-action="selectFolder"]': function (e) {
                var id = $(e.currentTarget).data('id');
                var model = this.collection.get(id);
                var name = this.translate('inbox', 'presetFilters', 'Email');
                if (model) {
                    name = model.get('name');
                }
                this.trigger('select', id, name);
                this.close();
            },
        },

        buttonList: [
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup: function () {
            this.header = '';
            this.wait(true);

            this.getCollectionFactory().create('EmailFolder', function (collection) {
                this.collection = collection;
                collection.maxSize = this.getConfig().get('emailFolderMaxCount') || 100;
                collection.data.boolFilterList = ['onlyMy'];
                collection.fetch().then(function () {
                    this.wait(false);
                }.bind(this));

            }, this);
        },
    });
});


