

Espo.define('treo-core:views/settings/fields/unit-configuration', 'view',
    Dep => Dep.extend({

        template: 'treo-core:settings/fields/unit-configuration/edit',

        validations: [],

        events: {
            'click a[data-action="createMeasure"]': function () {
                this.createMeasure();
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.name = this.options.name || this.options.defs.name;
            this.mode = this.options.mode || this.mode;

            this.getCollectionFactory().create('Settings', collection => {
                this.collection = collection;

                this.configuration = Espo.Utils.cloneDeep(this.getConfig().get('unitsOfMeasure') || {});
                Object.keys(this.configuration).forEach((measure, i) => {
                    i++;
                    let measureConfig = this.configuration[measure];
                    this.getModelFactory().create(null, model => {
                        model.set({
                            measure: measure,
                            units: measureConfig.unitList
                        });
                        model.setDefs({
                            fields: {
                                measure: {
                                    type: 'varchar'
                                },
                                units: {
                                    type: 'array'
                                }
                            }
                        });
                        model.id = i;
                        this.collection.add(model);
                        this.collection._byId[i] = model;
                    });
                });
                this.setupList();
            });

            this.listenTo(this, 'update-configuration', () => {
                this.setupList();
            });
        },

        setupList() {
            if (this.getView('list')) {
                this.clearView('list');
            }
            this.createView('list', 'treo-core:views/settings/record/unit-configuration-list', {
                collection: this.collection,
                el: `${this.options.el} .list-container`,
                layoutName: 'unitConfiguration',
                showCount: false,
                massActionsDisabled: true,
                showMore: false,
                checkAllResultDisabled: true,
                buttonsDisabled: true,
                rowActionsView: 'treo-core:views/settings/record/row-actions/only-quick-edit'
            }, view => {
                view.listenTo(view, 'update-configuration', () => this.trigger('update-configuration'));
                view.render();
            });
        },

        createMeasure() {
            Espo.Ui.notify(this.translate('loading', 'messages'));
            this.getModelFactory().create(null, model => {
                model.set({measure: '', units: []});

                this.createView('modal', 'treo-core:views/settings/modals/unit-edit', {
                    model: model
                }, view => {
                    view.once('after:render', function () {
                        Espo.Ui.notify(false);
                    });

                    this.listenToOnce(view, 'remove', () => {
                        this.clearView('modal');
                    });

                    this.listenToOnce(view, 'after:save', m => {
                        this.collection.add(m);
                        m.id = this.collection.models.length;
                        this.collection._byId[m.id] = m;
                        this.trigger('update-configuration');
                    });
                    view.render();
                });
            });
        },

        getUnitRates(option) {
            let result = {};
            (option.units || []).slice(1).forEach(item => result[item] = 1);
            return result;
        },

        fetch() {
            let configuration = {};
            this.collection.forEach(model => {
                let option = model.getClonedAttributes();
                configuration[option.measure] = {
                    unitList: option.units || [],
                    baseUnit: (option.units || [])[0],
                    unitRates: this.getUnitRates(option)
                }
            });
            return {[this.name]: configuration};
        },

        validate() {
            for (let i in this.validations) {
                let method = 'validate' + Espo.Utils.upperCaseFirst(this.validations[i]);
                if (this[method].call(this)) {
                    this.trigger('invalid');
                    return true;
                }
            }
            return false;
        }

    })
);