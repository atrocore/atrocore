
Espo.define('views/email/fields/select-template', 'views/fields/link', function (Dep) {

    return Dep.extend({

        type: 'link',

        foreignScope: 'EmailTemplate',

        editTemplate: 'email/fields/select-template/edit',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.on('change', function () {
                var id = this.model.get(this.idName);
                if (id) {
                    this.loadTemplate(id);
                }
            }, this);
        },

        getSelectPrimaryFilterName: function () {
            return 'actual';
        },

        loadTemplate: function (id) {
            var to = this.model.get('to') || '';
            var emailAddress = null;
            to = to.trim();
            if (to) {
                var emailAddress = to.split(';')[0].trim();
            }

            $.ajax({
                url: 'EmailTemplate/action/parse',
                data: {
                    id: id,
                    emailAddress: emailAddress,
                    parentType: this.model.get('parentType'),
                    parentId: this.model.get('parentId'),
                    relatedType: this.model.get('relatedType'),
                    relatedId: this.model.get('relatedId')
                },
                success: function (data) {
                    this.model.trigger('insert-template', data);

                    this.emptyField();
                }.bind(this),
                error: function () {
                    this.emptyField();
                }.bind(this)
            });
        },

        emptyField: function () {
            this.model.set(this.idName, null);
            this.model.set(this.nameName, '');
        }

    });

});
