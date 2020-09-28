

Espo.define('views/email-template/record/edit', ['views/record/edit', 'views/email-template/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            Detail.prototype.listenToInsertField.call(this);
        },

    });

});
