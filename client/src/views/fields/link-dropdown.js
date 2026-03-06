/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/link-dropdown', 'views/fields/colored-enum', function (Dep) {
    return Dep.extend({

        type: 'link',

        nameName: null,

        idName: null,

        foreignScope: null,

        foreignName: null,

        originalName: null,

        selectBoolFilterList: [],

        boolFilterData: {},

        fieldsToPassInParams: [],

        setup: function () {
            if (this.nameName == null) {
                this.nameName = this.name + 'Name';
            }

            if (this.idName == null) {
                this.idName = this.name + 'Id';
            }

            if (this.options.customBoolFilterData) {
                this.boolFilterData = {...this.boolFilterData, ...this.options.customBoolFilterData}
            }

            if (this.options.customSelectBoolFilters) {
                this.options.customSelectBoolFilters.forEach(item => {
                    if (!this.selectBoolFilterList.includes(item)) {
                        this.selectBoolFilterList.push(item);
                    }
                });
            }

            this.prepareDefaultValue();

            this.foreignScope = this.options.foreignScope || this.foreignScope;
            this.foreignScope = this.foreignScope || this.model.getFieldParam(this.name, 'entity') || this.model.getLinkParam(this.name, 'entity');
            this.foreignName = this.foreignName || this.params.foreignName || this.model.getLinkParam(this.name, 'foreignName') || this.model.getFieldParam(this.name, 'foreignName') || 'name';
            this.originalName = this.name;
            this.name = this.idName;

            this.fieldsToPassInParams = this.model.getFieldParam(this.originalName, 'fieldsToPassInParams') || []

            this.prepareOptionsList();

            Dep.prototype.setup.call(this);

            this.reloadListener();
        },

        reloadListener() {
            this.onModelReady(() => {
                // we reload the option list everytime any time a field to pass in param change
                if (this.model.getFieldParam(this.originalName, 'reloadListOnFieldParamChange')) {
                    this.fieldsToPassInParams.forEach(field => {
                        this.listenTo(this.model, `change:${field}`, () => {
                            this.notify('Loading..');
                            this.prepareOptionsList(true);
                            this.notify(false);
                            this.reRender();
                        });
                    });
                }
            });
        },

        prepareDefaultValue: function () {
            this.params.options = [];
            this.translatedOptions = {};

            if (this.model.has(this.idName)) {
                this.params.options.push(this.model.get(this.idName));
                this.translatedOptions[this.model.get(this.idName)] = this.model.get(this.nameName) || this.model.get(this.idName);
                this.params.default = this.model.get(this.idName);
            }
        },

        prepareOptionsList: function (clearCache = false) {
            this.params.options = [];
            this.translatedOptions = {};
            this.params.optionColors = {};
            let name = this.foreignName
            if (!name || name === 'name') {
                name = this.getNameField(this.foreignScope)
            }
            const [localizedName] = this.getLocalizedFieldData(this.foreignScope, name);
            const params = {
                maxSize: 300,
                sortBy: localizedName,
                where: this.getWhereFilter()
            };
            for (const key of this.fieldsToPassInParams) {
                if (!params[key]) {
                    params[key] = this.model.get(key);
                }
            }
            this.params.linkOptions = this.getLinkOptions(this.foreignScope, params, clearCache);
            this.params.linkOptions.forEach(option => {
                if (option.id) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option[localizedName] || option[name] || option.id;
                    this.params.optionColors[option.id] = option.color || null;
                }
            })
        },

        getValueForDisplay: function () {
            return this.model.get(this.idName);
        },

        getLabelText: function () {
            return this.options.labelText || this.translate(this.originalName, 'fields', this.model.name);
        },

        getWhereFilter() {
            return this.model.getFieldParam(this.originalName, 'where') || [];
        },

        fetch: function () {
            let data = Dep.prototype.fetch.call(this);
            data[this.name + 'Name'] = this.translatedOptions[data[this.name]]
            return data;
        },

    });
});