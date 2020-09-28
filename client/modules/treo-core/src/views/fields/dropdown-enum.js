

Espo.define('treo-core:views/fields/dropdown-enum', 'view',
    Dep => Dep.extend({

        template: 'treo-core:fields/dropdown-enum/base',

        optionsList: [],

        modelKey: null,

        storageKey: null,

        fieldModel: null,

        events: _.extend({
            'click .action[data-action="saveFilter"]': function (e) {
                let el = $(e.currentTarget);
                let name = el.data('name');
                let option = this.optionsList.find(option => option.name === name) || {};
                if (!option.selectable) {
                    e.stopPropagation();
                }
                this.actionSaveFilter(name);
            }
        }, Dep.prototype.events),

        data() {
            return {
                options: this.optionsList,
                selected: this.getSelectedLabel()
            }
        },

        setup() {
            this.name = this.options.name || this.name;
            this.scope = this.options.scope || this.scope || this.model.name;

            this.optionsList = this.options.optionsList || this.optionsList;
            this.prepareOptionsList();

            this.storageKey = this.options.storageKey || this.storageKey;
            if (this.storageKey) {
                let selected = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).selected;
                if (this.optionsList.find(option => option.name === selected)) {
                    this.selected = selected;
                }
            }
            this.selected = this.selected || (this.optionsList.find(option => option.selectable) || {}).name;
            this.modelKey = this.options.modelKey || this.modelKey;
            this.setDataToModel({[this.name]: this.selected});

            this.getModelFactory().create(null, model => {
                this.fieldModel = model;
            });

            this.listenTo(this, 'after:render', () => {
                this.options.hidden ? this.hide() : this.show();
                this.createFields();
            });
        },

        createFields() {
            this.optionsList.forEach(option => {
                if (option.name && option.field && (option.type || option.view)) {
                    let views = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).views || {};
                    let dataKeys = this.getFieldManager().getActualAttributeList(option.type, option.name);
                    dataKeys.forEach(key => {
                        let value = typeof views[key] !== 'undefined' ? views[key] : option.default;
                        this.setDataToModel({[key]: value}, true);
                    });

                    let view = option.view || this.getFieldManager().getFieldView(option.type) || 'views/fields/base';
                    this.createView(option.name, view, {
                        el: `${this.options.el} .dropdown-enum-menu li a .field[data-name="${option.name}"]`,
                        model: this.fieldModel,
                        name: option.name,
                        inlineEditDisabled: true,
                        mode: 'edit',
                        label: option.label
                    }, view => {
                        view.render();
                    });
                }
            });
        },

        prepareOptionsList() {
            this.optionsList.forEach(option => {
                option.label = option.label || this.getLanguage().translateOption(option.name, this.name, 'Global');
                if (option.field) {
                    option.html = `<div class="field" data-name="${option.name}"></div>`;
                }
                option.html = option.html || option.label;
            });
        },

        getSelectedLabel() {
            return (this.optionsList.find(option => option.name === this.selected) || {}).label;
        },

        actionSaveFilter(name) {
            let option = this.optionsList.find(option => option.name === name) || {};
            if (option.selectable) {
                this.selected = name;
            }
            if (this.storageKey) {
                let previousFilters = this.getStorage().get(this.storageKey, this.scope) || {};
                let currentFilterData = previousFilters[this.name] || {};
                currentFilterData.selected = this.selected;
                if (option.field) {
                    let field = this.getView(name);
                    if (field) {
                        let fieldData = field.fetch();
                        this.setDataToModel(fieldData);
                        currentFilterData.views = _.extend({}, currentFilterData.views, fieldData);
                    }
                }
                this.getStorage().set(this.storageKey, this.scope, _.extend(previousFilters, {[this.name]: currentFilterData}));
            }
            this.setDataToModel({[this.name]: this.selected});
            this.reRender();

            this.model.trigger('overview-filters-changed');
        },

        setDataToModel(data, isField) {
            if (Espo.Utils.isObject(data)) {
                Object.keys(data).forEach(item => {
                    if (this.modelKey) {
                        this.model[this.modelKey] = _.extend({}, this.model[this.modelKey] , {[item]: data[item]});
                    } else {
                        this.model.set({[item]: data[item]}, {silent: true});
                    }
                    if (isField) {
                        this.fieldModel.set({[item]: data[item]}, {silent: true});
                    }
                });
            }
        },

        getParentCell() {
            return this.$el.parent();
        },

        hide() {
            let cell = this.getParentCell();
            cell.addClass('hidden-cell');
        },

        show() {
            let cell = this.getParentCell();
            cell.removeClass('hidden-cell');
        }

    })
);