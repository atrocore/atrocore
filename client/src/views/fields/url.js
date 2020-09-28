

Espo.define('views/fields/url', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        type: 'url',

        listTemplate: 'fields/url/list',

        detailTemplate: 'fields/url/detail',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.params.trim = true;
        },

        data: function () {
            return _.extend({
                url: this.getUrl()
            }, Dep.prototype.data.call(this));
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                if (this.params.strip) {
                    this.$element.on('change', function () {
                        var value = this.$element.val() || '';
                        value = this.strip(value);
                        this.$element.val(value);
                    }.bind(this));
                }
            }
        },

        strip: function (value) {
            value = value.trim();
            if (value.indexOf('http://') === 0) {
                value = value.substr(7);
            } else if (value.indexOf('https://') === 0) {
                value = value.substr(8);
            }
            value = value.replace(/\/+$/, '');
            return value;
        },

        getUrl: function () {
            var url = this.model.get(this.name);
            if (url && url != '') {
                if (!(url.indexOf('http://') === 0) && !(url.indexOf('https://') === 0)) {
                    url = 'http://' + url;
                }
                return url;
            }
            return url;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (this.params.strip) {
                data[this.name] = this.strip(data[this.name]);
            }
            return data;
        }

    });
});
