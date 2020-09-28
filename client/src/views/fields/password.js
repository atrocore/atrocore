

Espo.define('views/fields/password', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'password',

        detailTemplate: 'fields/password/detail',

        editTemplate: 'fields/password/edit',

        validations: ['required', 'confirm'],

        events: {
            'click [data-action="change"]': function (e) {
                this.changePassword();
            }
        },

        changePassword: function () {
            this.$el.find('[data-action="change"]').addClass('hidden');
            this.$element.removeClass('hidden');
            this.changing = true;
        },

        data: function () {
            return _.extend({
                isNew: this.model.isNew(),
            }, Dep.prototype.data.call(this));
        },

        validateConfirm: function () {
            if (this.model.has(this.name + 'Confirm')) {
                if (this.model.get(this.name) != this.model.get(this.name + 'Confirm')) {
                    var msg = this.translate('fieldBadPasswordConfirm', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.changing = false;

            if (this.params.readyToChange) {
                this.changePassword();
            }
        },

        fetch: function () {
            if (!this.model.isNew() && !this.changing) {
                return {};
            }
            return Dep.prototype.fetch.call(this);
        }
    });
});


