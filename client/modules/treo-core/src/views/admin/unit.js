

Espo.define('treo-core:views/admin/unit', 'views/settings/record/edit',
    Dep => Dep.extend({

        template: 'treo-core:admin/unit-edit',

        layoutName: 'unit',

        initialUnitsOfMeasure: {},

        setup() {
            Dep.prototype.setup.call(this);

            this.initialUnitsOfMeasure = Espo.Utils.cloneDeep(this.getConfig().get('unitsOfMeasure') || {});

            this.listenTo(this.model, 'after:save', () => {
                let labels = this.getLabelsForSaveFromUnits();
                if (Object.keys(labels).length) {
                    this.ajaxPostRequest('LabelManager/action/saveLabels', {
                        labels: labels,
                        language: this.getPreferences().get('language') || this.getConfig().get('language'),
                        scope: 'Global'
                    });
                }
            });
        },

        getLabelsForSaveFromUnits() {
            let labels = {};
            let measurements = this.getModifiedMeasurements();

            Object.keys(measurements).forEach(measureName => {
                let translates = this.getMeasureTranslations();
                if (measurements[measureName].saveMeasure) {
                    let measureLabelName = `measure[.]${measureName}`;
                    labels[measureLabelName] = translates.measure || measureName;
                }
                let unitList = measurements[measureName].unitList || [];
                unitList.forEach(unitName => {
                    let unitLabelName = `unit ${measureName}[.]${unitName}`;
                    labels[unitLabelName] = translates.units[unitName] || unitName;
                });
            });
            return labels;
        },

        getModifiedMeasurements() {
            let initialUnitsOfMeasure = this.initialUnitsOfMeasure;
            let data = this.model.get('unitsOfMeasure') || {};
            let attrs = {};
            for (let name in data) {
                if (_.isEqual(data[name], initialUnitsOfMeasure[name])) {
                    continue;
                }
                attrs[name] = data[name];
                attrs[name].saveMeasure = !(name in initialUnitsOfMeasure);
            }
            return attrs;
        },

        getMeasureTranslations(measure) {
            let globalTranslates = (this.getLanguage().data || {})['Global'] || {};
            return {
                measure: globalTranslates['measure'][measure],
                units: globalTranslates[`unit${measure}`] || {}
            };
        }

    })
);

