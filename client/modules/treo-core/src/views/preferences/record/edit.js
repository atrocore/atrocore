

Espo.define('treo-core:views/preferences/record/edit', 'class-replace!treo-core:views/preferences/record/edit', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.showField('dashboardLayout');
            if (!this.model.get('isPortalUser')) {
                this.showPanel('notifications');
            }
        },
    });
});

