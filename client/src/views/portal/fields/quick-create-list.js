

Espo.define('views/portal/fields/quick-create-list', 'views/settings/fields/quick-create-list', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.params.options = this.params.options.filter(function (tab) {
                if (!!this.getMetadata().get('scopes.' + tab + '.aclPortal')) {
                    return true;
                }
            }, this);
        },

    });

});
