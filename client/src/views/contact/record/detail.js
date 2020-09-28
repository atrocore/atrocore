

Espo.define('views/contact/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlPortalUserVisibility();
            this.listenTo(this.model, 'change:portalUserId', this.controlPortalUserVisibility, this);
        },

        controlPortalUserVisibility: function () {
            if (this.model.get('portalUserId')) {
                this.showField('portalUser');
            } else {
                this.hideField('portalUser');
            }
        }

    });
});
