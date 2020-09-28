

Espo.define('treo-core:views/settings/modals/unit-edit', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:settings/modals/unit-edit',

        configuration: {},

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup() {
            Dep.prototype.setup.call(this);

            this.id = this.options.id;
            this.configuration = this.options.configuration;

            this.setupHeader();

            if (this.model) {
                this.getModelFactory().create(null, model => {
                    model = this.model.clone();
                    model.id = this.model.id;
                    model.defs = this.model.defs;
                    this.model = model;
                });
            }

            this.setupOptionFields();
        },

        setupOptionFields() {
            this.createView('measure', 'views/fields/varchar', {
                el: `${this.options.el} .field[data-name="measure"]`,
                model: this.model,
                name: 'measure',
                mode: 'edit',
                params: {
                    trim: true,
                    required: true,
                    readOnly: !!this.id
                }
            }, view => view.render());

            this.createView('units', 'views/fields/array', {
                el: `${this.options.el} .field[data-name="units"]`,
                model: this.model,
                name: 'units',
                mode: 'edit',
                params: {
                    noEmptyString: true,
                    required: true
                }
            }, view => view.render());
        },

        setupHeader() {
            let measure = this.getLanguage().translate('measure', 'fields', 'Global');
            if (!this.id) {
                this.header = `${this.getLanguage().translate('Create', 'labels', 'Global')} ${measure}`;
            } else {
                this.header = `${this.getLanguage().translate('Edit')}: ${measure}`;
            }
        },

        actionSave() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }
            this.trigger('after:save', this.model);
            this.close();
        },

        validate() {
            let notValid = false;
            let fields = this.nestedViews || {};
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },


    })
);