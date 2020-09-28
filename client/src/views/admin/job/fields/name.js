

Espo.define('views/admin/job/fields/name', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        getValueForDisplay: function () {
            if (this.mode == 'list' || this.mode == 'detail') {
                if (!this.model.get('name')) {
                    return this.model.get('serviceName') + ': ' + this.model.get('methodName');
                } else {
                    return this.model.get('name');
                }
            }
        }

    });
});

