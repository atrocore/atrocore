

Espo.define('views/admin/inbound-emails', 'views/settings/record/edit', function (Dep) {

    return Dep.extend({

        layoutName: 'inboundEmails',

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

    });

});

