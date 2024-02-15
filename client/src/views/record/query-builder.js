/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/query-builder', ['view', 'lib!Interact', 'lib!QueryBuilder'], function (Dep) {

    return Dep.extend({

        template: 'record/query-builder',

        filters: [],

        events: {
            'click button[data-action="search"]': function (e) {
                this.search();
            },
            'click button[data-action="reset-filter"]': function (e) {
                this.resetFilters();
            },
            'click button[data-action="add-attribute-filter"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                this.addAttributeFilter();
            },
        },

        setup() {
            this.entityType = this.collection.name;
            this.scope = this.options.scope || this.entityType;

            this.model = new this.collection.model();
            this.model.clear();

            // set translates
            $.fn.queryBuilder.regional['main'] = this.getLanguage().data.Global.queryBuilderFilter;
            $.fn.queryBuilder.defaults({lang_code: 'main'});
        },

        data() {
            return {
                hasAttributeButton: this.model.urlRoot === 'Product'
            };
        },

        initQueryBuilderFilter(rules = []) {
            this.$el.find('.query-builder').queryBuilder({
                allow_empty: true,
                operators: [
                    {type: 'contains'},
                    {type: 'not_contains'},
                    {type: 'equal'},
                    {type: 'not_equal'},
                    {type: 'less'},
                    {type: 'less_or_equal'},
                    {type: 'greater'},
                    {type: 'greater_or_equal'},
                    {type: 'between'},
                    {type: 'in'},
                    {type: 'not_in'},
                    {type: 'is_null'},
                    {type: 'is_not_null'},
                    {type: 'linked_with', nb_inputs: 1, multiple: true, apply_to: ['string']},
                    {type: 'not_linked_with', nb_inputs: 1, multiple: true, apply_to: ['string']},
                    {type: 'array_any_of', nb_inputs: 1, multiple: true, apply_to: ['string']},
                    {type: 'array_none_of', nb_inputs: 1, multiple: true, apply_to: ['string']},
                    {type: 'is_linked', nb_inputs: 0, apply_to: ['string']},
                    {type: 'is_not_linked', nb_inputs: 0, apply_to: ['string']},
                    {type: 'query_in', nb_inputs: 1, apply_to: ['string']},
                    {type: 'query_linked_with', nb_inputs: 1, apply_to: ['string']},
                ],
                rules: rules,
                filters: this.filters,
                plugins: {
                    sortable: {
                        icon: 'fas fa-sort'
                    }
                }
            });

            this.$el.find('.query-builder').on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
                this.model.trigger('afterUpdateRuleOperator', rule);
            });
        },

        prepareFilters(callback) {
            this.filters = [];

            let promiseList = [];
            $.each(this.getMetadata().get(['entityDefs', this.scope, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.filterDisabled) {
                    return;
                }

                const fieldType = Espo.Utils.camelCaseToHyphen(fieldDefs.type);
                const view = this.getMetadata().get(['fields', fieldType, 'view'], `views/fields/${fieldType}`);

                promiseList.push(new Promise(resolve => {
                    this.createView(field, view, {
                        name: field,
                        model: this.model,
                        defs: {
                            name: field,
                            params: {
                                attribute: null
                            }
                        },
                    }, view => {
                        let filter = view.createQueryBuilderFilter();
                        if (filter) {
                            this.filters.push(filter);
                        }
                        resolve();
                    });
                }));
            });

            Promise.all(promiseList).then(() => {
                callback();
            });
        },

        search() {
            this.collection.where = [];

            const rules = this.$el.find('.query-builder').queryBuilder('getRules');

            if (rules && rules.rules && rules.rules.length > 0) {
                this.collection.where = [rules];
            }

            this.collection.fetch().then(() => Backbone.trigger('after:search', this.collection));

            Backbone.Events.trigger('search', this);
        },

        resetFilters() {
            this.$el.find('.query-builder').queryBuilder('setRules', []);
            this.search();
        },

        addAttributeFilter() {
            const scope = 'Attribute';
            if (this.getAcl().check(scope, 'read')) {
                const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';
                this.notify('Loading...');
                this.createView('dialog', viewName, {
                    scope: scope,
                    multiple: false,
                    createButton: false,
                    massRelateEnabled: false
                }, dialog => {
                    dialog.render();
                    this.notify(false);
                    dialog.once('select', attribute => {
                        let rules = this.$el.find('.query-builder').queryBuilder('getRules');

                        const fieldType = Espo.Utils.camelCaseToHyphen(attribute.get('type'));
                        const view = this.getMetadata().get(['fields', fieldType, 'view'], `views/fields/${fieldType}`);

                        const name = `attr_${attribute.get('id')}`;

                        this.createView(name, view, {
                            name: name,
                            model: this.model,
                            defs: {
                                name: name,
                                params: {
                                    attribute: attribute
                                }
                            },
                        }, view => {
                            let filter = view.createQueryBuilderFilter();
                            if (filter) {
                                filter.label = attribute.get('name');

                                let ids = this.filters.map(item => {
                                    return item.id
                                });

                                if (!ids.includes(name)) {
                                    this.filters.push(filter);
                                    const $qb = this.$el.find('.query-builder');
                                    $qb.queryBuilder('destroy');
                                    $qb.addClass('query-builder');
                                    this.initQueryBuilderFilter(rules);
                                }
                            }
                        });
                    });
                });
            }
        },

        afterRender() {
            this.prepareFilters(() => {
                this.initQueryBuilderFilter();
            });
        },

    });
});

