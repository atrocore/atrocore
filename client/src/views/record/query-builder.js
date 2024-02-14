/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/query-builder', ['view', 'lib!QueryBuilder'], function (Dep) {

    return Dep.extend({

        template: 'record/query-builder',

        events: {
            'click button[data-action="search"]': function (e) {
                this.search();
            },
            'click button[data-action="reset-filter"]': function (e) {
                this.resetFilters();
            },
        },

        setup() {
            this.entityType = this.collection.name;
            this.scope = this.options.scope || this.entityType;

            this.model = new this.collection.model();
            this.model.clear();
        },

        data() {
            return {};
        },

        search() {
            this.collection.where = [];

            const qbRules = this.$el.find('.query-builder').queryBuilder('getRules');

            if (qbRules && qbRules.rules && qbRules.rules.length > 0) {
                this.collection.where = [qbRules];
            }

            this.collection.fetch().then(() => Backbone.trigger('after:search', this.collection));

            Backbone.Events.trigger('search', this);
        },

        resetFilters() {
            this.$el.find('.query-builder').queryBuilder('setRules', []);
            this.search();
        },

        afterRender() {
            let filters = [];
            let promiseList = [];
            $.each(this.getMetadata().get(['entityDefs', this.scope, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.filterDisabled) {
                    return;
                }

                const fieldType = Espo.Utils.camelCaseToHyphen(fieldDefs.type);
                const view = this.getMetadata().get(['fields', fieldType, 'view'], `views/fields/${fieldType}`);

                promiseList.push(new Promise(resolve => {
                    this.createView(field, view, {name: field, model: this.model}, view => {
                        let filter = view.createQueryBuilderFilter();
                        if (filter) {
                            filters.push(filter);
                        }
                        resolve();
                    });
                }));
            });

            Promise.all(promiseList).then(() => {
                if (filters.length > 0) {
                    // set translates
                    $.fn.queryBuilder.regional['main'] = this.getLanguage().data.Global.queryBuilderFilter;
                    $.fn.queryBuilder.defaults({lang_code: 'main'});

                    // init
                    this.$el.find('.query-builder').queryBuilder({
                        allow_empty: true,
                        operators: [
                            { type: 'contains' },
                            { type: 'not_contains' },
                            { type: 'equal' },
                            { type: 'not_equal' },
                            { type: 'less' },
                            { type: 'less_or_equal' },
                            { type: 'greater' },
                            { type: 'greater_or_equal' },
                            { type: 'between' },
                            { type: 'in' },
                            { type: 'not_in' },
                            { type: 'is_null' },
                            { type: 'is_not_null' },
                            { type: 'linked_with', nb_inputs: 1, multiple: true, apply_to: ['string'] },
                            { type: 'not_linked_with', nb_inputs: 1, multiple: true, apply_to: ['string'] },
                            { type: 'array_any_of', nb_inputs: 1, multiple: true, apply_to: ['string'] },
                            { type: 'array_none_of', nb_inputs: 1, multiple: true, apply_to: ['string'] },
                            { type: 'is_linked', nb_inputs: 0, apply_to: ['string'] },
                            { type: 'is_not_linked', nb_inputs: 0, apply_to: ['string'] },
                            { type: 'query_in', nb_inputs: 1, apply_to: ['string'] },
                            { type: 'query_linked_with', nb_inputs: 1, apply_to: ['string'] },
                        ],
                        rules: [],
                        filters: filters
                    });

                    this.$el.find('.query-builder').on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
                        this.model.trigger('afterUpdateRuleOperator', rule);
                    });
                }
            });
        },

    });
});

