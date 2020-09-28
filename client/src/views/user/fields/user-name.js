

Espo.define('views/user/fields/user-name', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            var userNameRegularExpression = this.getConfig().get('userNameRegularExpression') || '[^a-z0-9\-@_\.\s]';

            if (this.mode == 'edit') {
                this.$element.on('change', function () {
                    var value = this.$element.val();
                    var re = new RegExp(userNameRegularExpression, 'gi');
                    value = value.replace(re, '').replace(/[\s]/g, '_').toLowerCase();
                    this.$element.val(value);
                    this.trigger('change');
                }.bind(this));
            }
        }

    });

});
