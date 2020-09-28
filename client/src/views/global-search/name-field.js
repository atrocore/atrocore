

Espo.define('views/global-search/name-field', 'views/fields/base', function (Dep) {

    return Dep.extend({

        listTemplate: 'global-search/name-field',

        data: function () {
            return {
                scope: this.model.get('_scope'),
                name: this.model.get('name') || this.translate('None'),
                id: this.model.id,
                iconHtml: this.getHelper().getScopeColorIconHtml(this.model.get('_scope'))
            };
        }

    });
});
