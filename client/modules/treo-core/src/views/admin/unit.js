/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

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

