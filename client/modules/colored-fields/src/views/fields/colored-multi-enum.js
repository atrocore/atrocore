

Espo.define('colored-fields:views/fields/colored-multi-enum', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        listTemplate: 'colored-fields:fields/colored-multi-enum/detail',

        detailTemplate: 'colored-fields:fields/colored-multi-enum/detail',

        defaultBackgroundColor: 'ececec',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setColors();
                this.$element.on('change', this.setColors.bind(this));
                this.$element[0].selectize.on('dropdown_open', this.setSelectizeColors.bind(this));
                this.$element[0].selectize.on('change', this.setSelectizeColors.bind(this));
            }
        },

        setSelectizeColors() {
            window.setTimeout(() => {
                let values = this.$element[0].selectize.currentResults.items || [];
                values.forEach(item => {
                    let internalValue = item.id.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\');
                    this.$element[0].selectize.$dropdown_content.find(`.option[data-value='${item.id}']`).css(this.getFieldStyles(internalValue));
                });
            }, 10);
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.selectedValues = (data.selected || []).map(item => {
                return _.extend({
                    value: item,
                }, this.getFieldStyles(item));
            });
            data = _.extend(this.getFieldStyles(data.value), data);
            return data;
        },

        setColors() {
            let value = this.$element.val();
            let values = value.split(':,:');
            if (values.length) {
                values.forEach(item => {
                    let internalValue = item.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\');
                    this.$el.find(`[data-value='${item}']`).css(this.getFieldStyles(internalValue));
                });
            }
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
