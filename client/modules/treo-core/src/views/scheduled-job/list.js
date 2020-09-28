
Espo.define('treo-core:views/scheduled-job/list', ['class-replace!treo-core:views/list', 'views/list'], function (Dep, List) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#Admin/jobs',
                html: this.translate('Jobs', 'labels', 'Admin')
            });
        },

        afterRender: function () {
            List.prototype.afterRender.call(this);
        },

    });

});
