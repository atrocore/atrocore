

Espo.define('treo-core:views/queue-manager/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        getHeader() {
            const name = this.model.get('name') || this.model.id;
            const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
            const headerIconHtml = this.getHeaderIconHtml();

            return this.buildHeaderHtml([
                headerIconHtml +
                `<a href="${rootUrl}" class="action" data-action="navigateToRoot">` +
                    this.getLanguage().translate(this.scope, 'scopeNamesPlural') +
                `</a>`,
                name
            ]);
        },
    });
});

