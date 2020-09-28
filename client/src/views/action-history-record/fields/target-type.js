

Espo.define('views/action-history-record/fields/target-type', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);
            this.params.options = this.getMetadata().getScopeEntityList();
        }

    });
});
