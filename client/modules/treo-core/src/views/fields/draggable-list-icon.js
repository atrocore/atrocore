

Espo.define('treo-core:views/fields/draggable-list-icon', 'view',
    Dep => Dep.extend({

        template: 'treo-core:fields/draggable-list-icon/base',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.css({verticalAlign: 'middle'});
        }

    })
);