

Espo.define('treo-core:views/edit', 'class-replace!treo-core:views/edit',
    Dep => Dep.extend({
        getHeader: function () {
            const headerIconHtml = this.getHeaderIconHtml();
            const arr = [];
            let html = '';

            if (this.options.noHeaderLinks) {
                arr.push(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
            } else {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                arr.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');
            }

            if (this.model.isNew()) {
                arr.push(this.getLanguage().translate('New'));
            } else {
                let name = Handlebars.Utils.escapeExpression(this.model.get('name'));

                if (name === '') {
                    name = this.model.id;
                }

                if (this.options.noHeaderLinks) {
                    arr.push(name);
                } else {
                    arr.push('<a href="#' + this.scope + '/view/' + this.model.id + '" class="action">' + name + '</a>');
                }
            }
            return this.buildHeaderHtml(arr);
        },
    })
);