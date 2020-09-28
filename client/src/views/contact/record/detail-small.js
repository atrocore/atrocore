

Espo.define('views/contact/record/detail-small', ['views/record/detail-small', 'views/contact/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlPortalUserVisibility();
            this.listenTo(this.model, 'change:portalUserId', this.controlPortalUserVisibility, this);
        },

        controlPortalUserVisibility: function () {
            Detail.prototype.controlPortalUserVisibility.call(this);
        }

    });
});
