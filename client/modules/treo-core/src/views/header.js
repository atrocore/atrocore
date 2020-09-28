

Espo.define('treo-core:views/header', 'class-replace!treo-core:views/header', function (Dep) {

    return Dep.extend({

        template: 'treo-core:header',

        baseOverviewFilters: [
            {
                name: 'fieldsFilter',
                view: 'treo-core:views/fields/overview-fields-filter'
            },
            {
                name: 'localesFilter',
                view: 'treo-core:views/fields/overview-locales-filter'
            }
        ],

        updatedOverviewFilters: [],

        events: _.extend({
            'click a:not([data-action])': function(e){
                let path = e.currentTarget.getAttribute("href");
                e.preventDefault();
                this.getRouter().checkConfirmLeaveOut(function () {
                    this.getRouter().navigate(path, {trigger: true});
                }.bind(this), this, false);
            }
        }, Dep.prototype.events),

        data() {
            let data = Dep.prototype.data.call(this);
            data.overviewFilters = this.updatedOverviewFilters.map(filter => filter.name);
            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model && !this.model.isNew() && this.getMetadata().get(['scopes', this.scope, 'advancedFilters'])) {
                this.createOverviewFilters();
            }
        },

        createOverviewFilters() {
            this.updatedOverviewFilters = this.filterOverviewFilters();

            (this.updatedOverviewFilters || []).forEach(filter => {
                this.createView(filter.name, filter.view, {
                    el: `${this.options.el} .field[data-name="${filter.name}"]`,
                    model: this.model,
                    name: filter.name,
                    storageKey: 'overview-filters',
                    modelKey: 'advancedEntityView'
                }, view => view.render());
            });
        },

        filterOverviewFilters() {
            return (this.baseOverviewFilters || []).filter(filter => {
                if (filter.name === 'localesFilter') {
                    return this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length
                }
                return true;
            });
        }

    });
});

