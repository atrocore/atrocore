

Espo.define('multilang:views/admin/field-manager/edit', 'class-replace!multilang:views/admin/field-manager/edit', function (Dep) {

    return Dep.extend({

        afterRender: function () {
            // call parent
            Dep.prototype.afterRender.call(this);

            if (this.defs.hideMultilang) {
                $('div[data-name="readOnly"]').remove();
                $('div[data-name="required"]').remove();
                $('a[data-action="removeValue"]').remove();
                $('.array-control-container').remove();
                $('input[name="coloredValue"]').remove();
            }
        }
    });

});
