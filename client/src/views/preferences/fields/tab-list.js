
Espo.define('views/preferences/fields/tab-list', 'views/settings/fields/tab-list', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.params.options = this.params.options.filter(function (d) {
                if (this.getAcl().checkScope(d)) {
                    return true;
                }
            }, this);
        },

    });

});
