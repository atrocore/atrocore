Espo.define('views/user/fields/password-updated-at', 'views/fields/datetime', function (Dep) {

    return Dep.extend({

        detailTemplate: 'user/fields/password-updated-at',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const metadata = this.getMetadata().get(['entityDefs', 'User', 'fields', 'passwordUpdatedBy']);
            this.createView('passwordUpdatedBy', metadata.view || 'views/fields/user', {
                model: this.model,
                el: `${this.options.el} [data-name="passwordUpdatedBy"]`,
                name: 'passwordUpdatedBy',
                readonly: true
            }, view => {
                view.render();
            });
        }

    });

});
