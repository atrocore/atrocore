

Espo.define('colored-fields:views/fields/color', ['views/fields/varchar', 'lib!jscolor'],
    Dep => Dep.extend({

        detailTemplate: 'fields/base/edit',

        listTemplate: 'fields/base/edit',

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let input = this.$el.find('input').get(0);
            let picker = new jscolor(input);

            if (this.mode !== 'edit') {
                input.readOnly = true;
                picker.showOnClick = false;
            }
        }

    })
);
