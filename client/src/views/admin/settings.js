

Espo.define('views/admin/settings', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'settings',

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

        },

    });
});

