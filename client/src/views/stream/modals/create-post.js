

Espo.define('views/stream/modals/create-post', 'views/modal', function (Dep) {

    return Dep.extend({

        _template: '<div class="record">{{{record}}}</div>',

        setup: function () {
            this.header = this.translate('Create Post');

            this.buttonList = [
                {
                    name: 'post',
                    label: 'Post',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {
                        dialog.close();
                    }
                }
            ];

            this.wait(true);

            this.getModelFactory().create('Note', function (model) {
                this.createView('record', 'views/stream/record/edit', {
                    model: model,
                    el: this.options.el + ' .record'
                }, function (view) {
                    this.listenTo(view, 'after:save', function () {
                        this.trigger('after:save');
                    }, this);
                }, this);

                this.wait(false);
            }, this);
        },

        actionPost: function () {
            this.getView('record').save();
        }

    });

});