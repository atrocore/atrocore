<script lang="ts">
    import {onMount} from "svelte";
    import {Metadata} from "../../../utils/Metadata";
    import {Storage} from "../../../utils/Storage";
    import Rule from "./interfaces/Rule";

    export let scope: string;
    export let collection: any;
    export let createView: Function;

    let filters: [];

    let queryBuilderElement: HTMLElement

    let model = new collection.model();

    function camelCaseToHyphen(str: string) {
        return str.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    }

    function getRulesIds(rules: Rule[]) {
        let ids: string[] = [];
        rules.forEach(rule => {
            if (rule.rules) {
                getRulesIds(rule.rules).forEach(innerId => {
                    ids.push(innerId);
                });
            } else if (rule.id) {
                ids.push(rule.id);
            }
        })

        return ids;
    }

    function  initQueryBuilderFilter() {
        const $queryBuilder = window.$(queryBuilderElement)
        const rules = Storage.get('queryBuilderRules', model.urlRoot) || [];

        $queryBuilder.queryBuilder({
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
            filters: filters,
            plugins: {
                sortable: {
                    icon: 'fas fa-sort'
                }
            },

        });

        model.trigger('afterInitQueryBuilder');
        $queryBuilder.on('rulesChanged.queryBuilder', (e, rule) => {
            try {
                const rules = $queryBuilder.queryBuilder('getRules');
                window.queryBuilder = $queryBuilder;
                console.log('rules', rules, 'e', e, 'rule', rule);
                if(rules) {
                    Storage.set('queryBuilderRules', model.urlRoot, rules);
                }
            } catch (err) {
            }
            model.trigger('rulesChanged', rule);
        });
        $queryBuilder.on('afterUpdateGroupCondition.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateGroupCondition', rule);
        });
        $queryBuilder.on('afterUpdateRuleFilter.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleFilter', rule);
        });
        $queryBuilder.on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleOperator', rule);
        });
        $queryBuilder.on('afterUpdateRuleValue.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleValue', rule);
        });
        $queryBuilder.on('afterAddGroup.queryBuilder', (e, rule) => {
            model.trigger('afterAddGroup', rule);
        });
        $queryBuilder.on('afterDeleteGroup.queryBuilder', (e, rule) => {
            model.trigger('afterDeleteGroup', rule);
        });
        $queryBuilder.on('afterAddRule.queryBuilder', (e, rule) => {
            model.trigger('afterAddRule', rule);
        });
        $queryBuilder.on('afterDeleteRule.queryBuilder', (e, rule) => {
            model.trigger('afterDeleteRule', rule);
        });
    }

    function  prepareFilters(callback: Function) {
        filters = [];
        let promiseList: Promise[] = [];
        Object.entries(Metadata.get(['entityDefs', scope, 'fields'])).forEach(([field, fieldDefs]) => {
            if (fieldDefs.filterDisabled) {
                return;
            }

            const fieldType = camelCaseToHyphen(fieldDefs.type);
            const view = Metadata.get(['fields', fieldType, 'view']) ||  `views/fields/${fieldType}`;

            promiseList.push(new Promise(resolve => {
                createView(field, view, {
                    name: field,
                    model: model,
                    defs: {
                        name: field,
                        params: {
                            attribute: null
                        }
                    },
                }, view => {
                    let filter = view.createQueryBuilderFilter();
                    if (filter) {
                        filters.push(filter);
                    }
                    resolve();
                });
            }));
        });

        Promise.all(promiseList).then(() => {
            callback();
        });
    }


    onMount(() => {
        prepareFilters(() => {
            initQueryBuilderFilter();
        });
    })
</script>

<div class="query-builder" bind:this={queryBuilderElement}></div>