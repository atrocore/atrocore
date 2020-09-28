
Espo.define('views/email/fields/person-string-data', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        listTemplate: 'email/fields/person-string-data/list',

        getAttributeList: function () {
            return ['personStringData', 'isReplied'];
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.isReplied = this.model.get('isReplied');

            return data;
        }

    });
});
