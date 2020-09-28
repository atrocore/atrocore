

Espo.define('views/fields/json-object', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'jsonObject',

        listTemplate: 'fields/json-object/detail',

        detailTemplate: 'fields/json-object/detail',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.valueIsSet = this.model.has(this.name);
            data.isNotEmpty = !!this.model.get(this.name);
            return data;
        },

        getValueForDisplay: function () {
            if (!this.model.get(this.name)) return null;
            var text = JSON.stringify(this.model.get(this.name), false, 2).replace(/(\r\n|\n|\r)/gm, '<br>').replace(/\s/g, '&nbsp;');

            return text;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        }

    });
});

