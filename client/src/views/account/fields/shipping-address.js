

Espo.define('Views.Account.Fields.ShippingAddress', 'Views.Fields.Address', function (Dep) {

    return Dep.extend({

        copyFrom: 'billingAddress',

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'edit') {
                var label = this.translate('Copy Billing', 'labels', 'Account');
                $btn = $('<button class="btn btn-default btn-sm">' + label + '</button>').on('click', function () {
                    this.copy(this.copyFrom);
                }.bind(this));
                this.$el.append($btn);
            }
        },

        copy: function (fieldFrom) {
            var attrList = Object.keys(this.getMetadata().get('fields.address.fields')).forEach(function (attr) {
                destField = this.name + Espo.Utils.upperCaseFirst(attr);
                sourceField = fieldFrom + Espo.Utils.upperCaseFirst(attr);

                this.model.set(destField, this.model.get(sourceField));
            }, this);

        },

    });
});

