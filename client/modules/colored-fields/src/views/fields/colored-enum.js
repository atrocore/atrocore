

Espo.define('colored-fields:views/fields/colored-enum', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        listTemplate: 'colored-fields:fields/colored-enum/detail',

        detailTemplate: 'colored-fields:fields/colored-enum/detail',

        editTemplate: 'colored-fields:fields/colored-enum/edit',

        defaultBackgroundColor: 'ececec',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.$el.find(`select[name="${this.name}"]`).on('change', function () {
                    this.$element.css(this.getFieldStyles(this.$element.val()));
                }.bind(this));
            }
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.options = (data.params.options || []).map(item => {
                return _.extend({
                    selected: item === data.value,
                    value: item
                }, this.getFieldStyles(item));
            });
            data = _.extend(this.getFieldStyles(data.value), data);
            return data;
        },

        getFieldStyles(fieldValue) {
            let backgroundColor = this.getBackgroundColor(fieldValue);
            let fontSize = this.model.getFieldParam(this.name, 'fontSize');
            let data = {
                backgroundColor: backgroundColor,
                color: this.getFontColor(backgroundColor),
                fontWeight: 'normal'
            };
            if (this.mode !== 'edit') {
                data.fontSize = fontSize ? fontSize + 'em' : '100%';
            }
            return data;
        },

        getBackgroundColor(fieldValue) {
            return '#' + ((this.model.getFieldParam(this.name, 'optionColors') || {})[fieldValue] || this.defaultBackgroundColor);
        },

        getFontColor(backgroundColor) {
            let color = '#000';
            if (backgroundColor) {
                backgroundColor = backgroundColor.slice(1);
                let r = parseInt(backgroundColor.substr(0, 2), 16);
                let g = parseInt(backgroundColor.substr(2, 2), 16);
                let b = parseInt(backgroundColor.substr(4, 2), 16);
                let l = 1 - ( 0.299 * r + 0.587 * g + 0.114 * b) / 255;
                if (l >= 0.5) {
                    color = '#fff';
                }
            }
            return color;
        }
    });

});
