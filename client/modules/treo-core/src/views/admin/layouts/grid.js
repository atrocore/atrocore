

Espo.define('treo-core:views/admin/layouts/grid', 'class-replace!treo-core:views/admin/layouts/grid',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const cells = this.$el.find('div#layout.row ul.cells li.cell');

            cells.each((index, element) => {
                const name = $(element).data('name');

                if (name) {
                    const text = this.translate(name, 'fields', this.scope);

                    element.setAttribute('title', text)
                }
            })
        }

    })
);


