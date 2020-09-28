

Espo.define('views/contact/modals/select-for-portal-user', 'views/modals/select-records', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.buttonList.unshift({
                name: 'skip',
                html: this.translate('Proceed w/o Contact', 'labels', 'User')
            });
        },

        actionSkip: function () {
            this.trigger('skip');
            this.remove();
        }

    });
});
