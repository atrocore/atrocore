

Espo.define('views/note/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        sideView: null,

        isWide: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlRequiredFields();
            this.listenTo(this.model, 'change:attachmentsIds', function () {
                this.controlRequiredFields();
            }, this);
        },

        controlRequiredFields: function () {
            if (!(this.model.get('attachmentsIds') || []).length) {
                this.setFieldRequired('post');
            } else {
                this.setFieldNotRequired('post');
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);


        }

    });
});


