

Espo.define('views/stream/message', 'view', function (Dep) {

    return Dep.extend({

        setup: function () {
            var template = this.options.messageTemplate;
            var data = this.options.messageData;

            for (var key in data) {
                var value = data[key] || '';

                if (value.indexOf('field:') === 0) {
                    var field = value.substr(6);
                    this.createField(key, field);

                    template = template.replace('{' + key +'}', '{{{' + key +'}}}');
                } else {
                    template = template.replace('{' + key +'}', value);
                }
            }

            this._template = template;
        },

        createField: function (key, name, type, params) {
            type = type || this.model.getFieldType(name) || 'base';
            this.createView(key, this.getFieldManager().getViewName(type), {
                model: this.model,
                defs: {
                    name: name,
                    params: params || {}
                },
                mode: 'detail',
                readOnly: true
            });
        }

    });
});

