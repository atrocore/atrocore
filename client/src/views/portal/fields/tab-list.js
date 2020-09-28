

Espo.define('views/portal/fields/tab-list', 'views/settings/fields/tab-list', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = this.params.options.filter(function (tab) {
                if (tab === '_delimiter_') return true;
                if (!!this.getMetadata().get('scopes.' + tab + '.aclPortal')) {
                    return true;
                }
            }, this);
        }

    });

});
