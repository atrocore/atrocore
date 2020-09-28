

Espo.define('views/admin/formula/modals/add-attribute', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        _template: '<div class="attribute" data-name="attribute">{{{attribute}}}</div>',

        setup: function () {
            this.header = this.translate('Attribute');
            this.scope = this.options.scope;

            var model = new Model();

            this.createView('attribute', 'views/admin/formula/fields/attribute', {
                el: this.getSelector() + ' [data-name="attribute"]',
                model: model,
                mode: 'edit',
                scope: this.scope,
                defs: {
                    name: 'attribute',
                    params: {}
                }
            }, function (view) {
                this.listenTo(view, 'change', function () {
                    var list = model.get('attribute') || [];
                    if (!list.length) return;
                    this.trigger('add', list[0]);
                }, this);
            });
        }

    });
});

