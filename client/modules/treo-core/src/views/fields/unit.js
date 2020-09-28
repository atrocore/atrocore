

Espo.define('treo-core:views/fields/unit', 'views/fields/float',
    Dep => Dep.extend({

        type: 'unit',

        editTemplate: 'treo-core:fields/unit/edit',

        detailTemplate: 'treo-core:fields/unit/detail',

        listTemplate: 'treo-core:fields/unit/list',

        data() {
            let data = Dep.prototype.data.call(this);

            data.unitFieldName = this.unitFieldName;
            data.unitList = this.unitList;
            data.unitListTranslates = this.unitListTranslates;
            data.unitValue = this.model.get(this.unitFieldName);
            data.unitValueTranslate = this.unitListTranslates[data.unitValue] || data.unitValue;
            data.valueOrUnit = !!(data.value || data.unitValue);
            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.unitFieldName = this.name + 'Unit';
            this.unitList = this.getUnitList();
            this.unitListTranslates = ((this.getLanguage().data || {}).Global || {})[`unit ${this.params.measure}`] || {};
        },

        getUnitList() {
            let unitList = [];
            let measure = this.params.measure;
            if (measure) {
                let unitsOfMeasure = this.getConfig().get('unitsOfMeasure') || {};
                let measureConfig = unitsOfMeasure[measure] || {};
                unitList = measureConfig.unitList || [];
            }
            return unitList;
        },

        formatNumber(value) {
            if (this.mode === 'list' || this.mode === 'detail') {
                return this.formatNumberDetail(value);
            }
            return this.formatNumberEdit(value);
        },

        formatNumberEdit(value) {
            if (value !== null) {
                let parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);

                return parts.join(this.decimalMark);
            }
            return '';
        },

        formatNumberDetail(value) {
            if (value !== null) {
                let parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);

                return parts.join(this.decimalMark);
            }
            return '';
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.$unit = this.$el.find(`[name="${this.unitFieldName}"]`);
                this.$unit.on('change', function () {
                    this.model.set(this.unitFieldName, this.$unit.val());
                }.bind(this));
            }
        },

        fetch: function () {
            let data = {};

            let value = this.$element.val();
            value = this.parse(value);

            data[this.name] = value;
            data[this.unitFieldName] = this.$unit.val();

            return data;
        },
    })
);

