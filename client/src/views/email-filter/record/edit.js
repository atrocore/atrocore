

Espo.define('views/email-filter/record/edit', ['views/record/edit', 'views/email-filter/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            Detail.prototype.setupFilterFields.call(this);
        },

        controlIsGlobal: function () {
            Detail.prototype.controlIsGlobal.call(this);
        },

        controlEmailFolder: function () {
            Detail.prototype.controlEmailFolder.call(this);
        }

    });

});

