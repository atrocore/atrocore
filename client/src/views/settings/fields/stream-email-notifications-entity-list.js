

Espo.define('views/settings/fields/stream-email-notifications-entity-list', 'views/fields/entity-type-list', function (Dep) {

    return Dep.extend({

        setupOptions: function () {

            Dep.prototype.setupOptions.call(this);

            this.params.options = this.params.options.filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return;
                if (!this.getMetadata().get('scopes.' + scope + '.object')) return;
                if (!this.getMetadata().get('scopes.' + scope + '.stream')) return;
                return true;
            }, this)
        },

    });
});
