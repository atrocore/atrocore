

Espo.define('treo-core:views/modals/edit', 'class-replace!treo-core:views/modals/edit',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            if (!this.id) {
                this.header = `${this.getLanguage().translate(this.scope, 'scopeNames')}: ${this.translate('New')}`;
            } else {
                this.header = this.getLanguage().translate('Edit');
                this.header += ': ' + this.getLanguage().translate(this.scope, 'scopeNames');
            }

            if (!this.fullFormDisabled) {
                if (!this.id) {
                    this.header = '<a href="#' + this.scope + '/create" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                } else {
                    this.header = '<a href="#' + this.scope + '/edit/' + this.id+'" class="action" title="'+this.translate('Full Form')+'" data-action="fullForm">' + this.header + '</a>';
                }
            }

            const iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header = iconHtml + this.header;
        }
    })
);