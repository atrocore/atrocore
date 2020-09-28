

Espo.define('views/portal/fields/custom-id', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'change', function () {
                var value = this.model.get('customId');
                if (!value || value === '') return;

                value = value.replace(/ /i, '-').toLowerCase();

                value = encodeURIComponent(value);

                this.model.set('customId', value);
            }, this);
        },

    });

});
