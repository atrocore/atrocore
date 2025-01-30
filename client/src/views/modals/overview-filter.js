/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/modals/overview-filter', 'views/modal', function (Modal) {
    return Modal.extend({
        template: 'modals/overview-filter',
        overviewFilters: [],
        currentValues: {},
        filterModel: null,
        events: {
            'click [data-name="apply"]': function () {
                this.trigger('save', this.filterModel);

                this.close();
            },
            'click [data-name="reset"]': function () {
                 this.overviewFilters.forEach(filter => {
                     this.filterModel.set(filter.name, [filter.defaultValue])
                 });
            }
        },
        setup() {
            Modal.prototype.setup.call(this);
            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                },
                {
                    name: 'reset',
                    label: 'Reset all',
                    style: "reset"
                }
            ];

            this.scope = this.options.scope;
            this.model = this.options.model;

            this.overviewFilters = this.options.overviewFilters ?? [];

            this.currentValues = this.options.currentValues ?? {};

            this.getModelFactory().create(null, model => {
                this.filterModel = model;
                this.overviewFilters.forEach(filter => {
                    this.createOverviewFilter(filter, model);
                });
            });
        },

        data() {
            return {
                overviewFilters: this.overviewFilters.map(v => {
                    return {
                        name: v.name,
                        label: v.label
                    }
                })
            }
        },

        createOverviewFilter(filter, model) {
            let options = filter.options;
            let translatedOptions = {};
            if (filter.translatedOptions) {
                translatedOptions = filter.translatedOptions;
            } else {
                options.forEach(option => {
                    translatedOptions[option] = this.getLanguage().translateOption(option, filter.name, 'Global');
                });
            }

            let selected = [filter.defaultValue ?? options[0]];
            if (this.currentValues[filter.name]) {
                selected = [];
                (this.currentValues[filter.name] || []).forEach(option => {
                    if (options.includes(option)) {
                        selected.push(option);
                    }
                });
                if (selected.length === 0) {
                    selected = [options[0]]
                }
            }

            model.set(filter.name, selected);

            this.createView(filter.name, 'views/fields/multi-enum', {
                el: `${this.options.el} .field[data-name="${filter.name}"]`,
                name: filter.name,
                mode: 'edit',
                model: model,
                dragDrop: false,
                params: {
                    options: options,
                    translatedOptions: translatedOptions
                }
            }, view => {
                let all = options[0];
                this.listenTo(model, `change:${filter.name}`, () => {
                    let last = Espo.Utils.cloneDeep(model.get(filter.name)).pop();
                    let values = [];
                    if (last === all) {
                        values = [all];
                    } else {
                        options.forEach(option => {
                            if (model.get(filter.name).includes(option)) {
                                values.push(option);
                            }
                        });
                        if (values.length === 0) {
                            values = [all];
                        }
                        // delete "all" if it needs
                        if (values.includes(all) && values.length > 1) {
                            values.shift();
                        }

                        if (filter.selfExcludedFieldsMap) {
                            const excludedValue = filter.selfExcludedFieldsMap[last];
                            const key = values.findIndex(item => item === excludedValue)

                            if (key !== -1) {
                                values.splice(key, 1);
                            }
                        }
                    }

                    model.set(filter.name, values, {trigger: false});
                    view.reRender();
                });
                view.render();
            });
        },
    })
});