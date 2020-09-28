

Espo.define('views/fields/colorpicker', ['views/fields/varchar', 'lib!Colorpicker'], function (Dep, Colorpicker) {

    return Dep.extend({

        type: 'varchar',

        detailTemplate: 'fields/colorpicker/detail',

        listTemplate: 'fields/colorpicker/detail',

        editTemplate: 'fields/colorpicker/edit',

        forceTrim: true,

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode == 'edit') {
                this.$element.parent().colorpicker({
                    format: 'hex'
                });
            }
            if (this.mode === 'edit') {
                this.$element.on('change', function () {
                    if (this.$element.val() === '') {
                        this.$el.find('.input-group-addon > i').css('background-color', 'transparent');
                    }
                }.bind(this));
            }
        }

    });
});
