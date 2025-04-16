<script lang="ts">
    import {onMount} from "svelte";
    import {Metadata} from "../../../utils/Metadata";
    import {Storage} from "../../../utils/Storage";
    import Rule from "./interfaces/Rule";
    import {Acl} from "../../../utils/Acl";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import {UserData} from "../../../utils/UserData";

    export let scope: string;
    export let collection: any;
    export let createView: Function;

    export let mandatoryBoolFilter: string[] = [];

    export let parentWidth: number;

    let filters: [];

    let boolFilterList: [];

    let selectedFilterList: Array<any> = [];

    let queryBuilderElement: HTMLElement

    let model = new collection.model();

    $: {
        updateStyle(parentWidth);
    }

    function updateStyle(parentWidth: number) {
        let rules = [
            '.query-builder .rule-container .rule-filter-container',
            '.query-builder .rule-container .rule-operator-container',
            '.query-builder .rule-container .rule-value-container'
        ];

        for (const rule of rules) {
            let elements = document.querySelectorAll(rule);
            if (!elements.length) {
                return;
            }
            for (const element of elements) {
                if( element.style.display === 'none') {
                    continue;
                }
                if (parentWidth < 450) {
                    element.style.display = 'block'
                    element.style.width = '100%'
                } else {
                    element.style.display = 'inline-block';
                    element.style.width = '25%'
                    if (rule.includes('operator')) {
                        element.style.width = '20%'
                    }
                }
            }
        }
        let deleteButton = document.querySelector('.query-builder .rule-actions');
        if (deleteButton) {
            if (parentWidth > 450) {
                deleteButton.style.marginTop = '10px';
                deleteButton.style.marginBottom = '5px';

            } else {
                deleteButton.style.marginTop = '0';
                deleteButton.style.marginBottom = '0';
            }
        }
    }

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

    function initQueryBuilderFilter() {
        const $queryBuilder = window.$(queryBuilderElement)
        const rules = Storage.get('queryBuilderRules', scope) || [];
        const emptyAttribute = 'emptyAttributeRule';

        let filterPerGroups = {};
        for (const filter of filters) {
            let group = filter.optgroup ?? 'default'
            filterPerGroups[group] = filterPerGroups[group] ?? [];
            filterPerGroups[group].push(filter);
        }

        for (const filterPerGroupsKey in filterPerGroups) {
            filterPerGroups[filterPerGroupsKey].sort(function (v1, v2) {
                return v1.label.localeCompare(v2.label);
            });
        }

        if(hasAttribute()) {
            let attributeButton = {
                id: emptyAttribute,
                label: `[${Language.translate('addAttribute')}]`,
                type: 'boolean',
                optgroup: Language.translate('Attributes'),
                operators: ['equal',],
                input: 'radio',
                values: {
                    0: 'false'
                }
            };
            if (!filterPerGroups[Language.translate('Attributes')]) {
                filterPerGroups[Language.translate('Attributes')] = [attributeButton]
            } else {
                filterPerGroups[Language.translate('Attributes')] = filterPerGroups[Language.translate('Attributes')].filter(v => v.id !== attributeButton.id)
                filterPerGroups[Language.translate('Attributes')].unshift(attributeButton);
            }
        }


        filters = [
            ...(filterPerGroups[Language.translate('Attributes')] ?? []),
            ...(filterPerGroups['default'] ?? []),
            ...(filterPerGroups[Language.translate('Fields')] ?? []),
        ]

        $queryBuilder.queryBuilder({
            allow_empty: true,
            select_placeholder: Language.translate('filterPlaceHolder'),
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
        updateStyle(parentWidth);
        $queryBuilder.on('rulesChanged.queryBuilder', (e, rule) => {
            setTimeout(() => {
                updateStyle(parentWidth);
            }, 500)

            try {
                const rules = $queryBuilder.queryBuilder('getRules');
                if (rules) {
                    Storage.set('queryBuilderRules', scope, rules);
                }
            } catch (err) {
            }
            model.trigger('rulesChanged', rule);
        });

        $queryBuilder.on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleOperator', rule);
        });

        $queryBuilder.on('afterAddGroup.queryBuilder', (e, rule) => {
            model.trigger('afterAddGroup', rule);
            updateStyle(parentWidth);
        });

        $queryBuilder.on('afterAddRule.queryBuilder', (e, rule) => {
            setTimeout(() => {
                updateStyle(parentWidth);
            }, 500)
            model.trigger('afterAddRule', rule);
        });
    }

    function prepareFilters(callback: Function) {

        filters = [];

        let promiseList: Promise[] = [];

        Object.entries(Metadata.get(['entityDefs', scope, 'fields'])).forEach(([field, fieldDefs]) => {
            if (fieldDefs.filterDisabled) {
                return;
            }

            const fieldType = camelCaseToHyphen(fieldDefs.type);
            const view = Metadata.get(['fields', fieldType, 'view']) || `views/fields/${fieldType}`;


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


        const rules = Storage.get('queryBuilderRules', scope);

        /**
         * Load attributes filters
         */
        if (rules.rules) {
            promiseList.push(new Promise(resolve => {
                let attributesIds = [];
                getRulesIds(rules.rules).forEach(id => {
                    let parts = id.split('_');
                    if (parts.length === 2 && parts[0] === 'attr') {
                        attributesIds.push(parts[1]);
                    }
                })

                if (attributesIds.length > 0) {
                    const where = [{attribute: 'id', type: 'in', value: attributesIds}];
                    let userData = UserData.get();
                    fetch('/api/v1/Attribute?' + window.$.param({where}), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
                        },
                    }).then(response => {

                        return response.json().then(attrs => {
                            if (attrs.list) {
                                attrs.list.forEach(attribute => {
                                    pushAttributeFilter(attribute, (pushed, filter) => {
                                        resolve();
                                    })
                                });
                            } else {
                                resolve();
                            }
                        });
                    });
                } else {
                    resolve();
                }
            }));
        }

        Promise.all(promiseList).then(() => {
            callback();
        });
    }

    function applyFilter() {
        let validation = window.$(queryBuilderElement).queryBuilder('validate');
        if (!validation) {
            Notifier.notify(Language.translate('They are some errors on your query'), 'error')
        } else {
            updateCollection();
        }
    }

    function resetFilter() {
        Storage.set('queryBuilderRules', scope, []);
        window.$(queryBuilderElement).queryBuilder('reset')
    }

    function updateCollection() {
        collection.reset();
        Notifier.notify('Please wait...');
        let where = [Storage.get('queryBuilderRules', scope)];
        if(selectedFilterList.length || mandatoryBoolFilter.length) {
            where.push({'type': 'bool', value: [...selectedFilterList, ...mandatoryBoolFilter]})
        }

        collection.where = where;

        collection.fetch().then(() => window.Backbone.trigger('after:search', collection));
    }

    function pushAttributeFilter(attribute: any, callback: Function) {
        const fieldType = window.Espo.Utils.camelCaseToHyphen(attribute.type);
        const view = Metadata.get(['fields', fieldType, 'view']) ?? `views/fields/${fieldType}`;

        const name = `attr_${attribute.id}`;

        createView(name, view, {
            name: name,
            model: model,
            defs: {
                name: name,
                params: {
                    attribute: attribute
                }
            },
        }, view => {
            let filter = view.createQueryBuilderFilter();
            if (filter) {
                filter.label = attribute.name;
                filter.optgroup = Language.translate('Attributes');
                let ids = filters.map(item => {
                    return item.id
                });
                if (!ids.includes(name)) {
                    filters.push(filter);
                    callback(true, filter);
                } else {
                    callback(false, filter);
                }
            }
        });
    }

    function hasAttribute() {
        return Metadata.get(['scopes', scope, 'hasAttribute']) || scope === 'Product';
    }

    function addAttributeFilter(callback) {
        const attributeScope = 'Attribute';
        if (Acl.check(scope, 'read')) {
            const viewName = Metadata.get(['clientDefs', attributeScope, 'modalViews', 'select']) || 'views/modals/select-records';
            Notifier.notify('Loading...');
            createView('dialog', viewName, {
                scope: attributeScope,
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false
            }, dialog => {
                dialog.render();
                Notifier.notify(false);
                dialog.dialog.$el.on('hidden.bs.modal', (e) => {
                    if (callback) {
                        callback(false)
                    }
                });
                dialog.listenTo(dialog, 'cancel, close', () => {
                    if (callback) {
                        callback(false)
                    }
                })
                dialog.once('select', attribute => {
                    pushAttributeFilter(attribute.attributes, (pushed, filter) => {
                        if (callback) {
                            callback(pushed, filter);
                        }
                    })

                });
            });
        }
    }


    function handleGeneralFilterChecked(e, filter) {
        let isChecked = e.target.checked;
        if(isChecked) {
            selectedFilterList = [...selectedFilterList, filter]
        }else{
            selectedFilterList = [...selectedFilterList.filter(v => v !== filter)];
        }

        Storage.set('selectedFilterList', scope, selectedFilterList);

        updateCollection()
    }

    function unsetAll() {
        resetFilter();
        selectedFilterList = [];
        Storage.clear('selectedFilterList', scope)
    }

    onMount(() => {
        boolFilterList = (Metadata.get(['clientDefs', scope, 'boolFilterList']) || []).filter(function (item) {
            if (typeof item === 'string') return true;
            item = item || {};
            if (item.accessDataList) {
                if (!window.Espo.Utils.checkAccessDataList(item.accessDataList, Acl, Acl.getUser())) {
                    return false;
                }
            }
            return true;
        }).map(function (item) {
            if (typeof item === 'string') return item;
            item = item || {};
            return item.name;
        });

        let hiddenBoolFilterList = Metadata.get(['clientDefs', scope, 'hiddenBoolFilterList']) || [];
        boolFilterList = boolFilterList.filter(function (item) {
            return !hiddenBoolFilterList.includes(item)
        });

        selectedFilterList = Storage.get('selectedFilterList', scope) ?? [];

        selectedFilterList = selectedFilterList.filter(function (item) { return boolFilterList.includes(item)})

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('where')) {
            const where = JSON.parse(urlParams.get('where'));
            if (where) {
                Storage.set('queryBuilderRules', scope, where);
                applyFilter();
                window.history.replaceState({}, document.title, window.location.origin + '#' + scope);
            }
        }

        // set translates
        window.$.fn.queryBuilder.regional['main'] = Language.getData().Global.queryBuilderFilter;

        let originalUpdateRuleFilter = window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter;
        window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter = function (rule, previousFilter) {
            if (rule.filter.id === 'emptyAttributeRule') {
                addAttributeFilter((pushed, filter) => {
                    if (pushed) {
                        this.setFilters(filters)
                        rule.filter = filter;
                        originalUpdateRuleFilter.call(this, rule, previousFilter);
                    }else{
                        if(rule.filter.id === 'emptyAttributeRule') {
                            rule.filter = previousFilter;
                        }
                        originalUpdateRuleFilter.call(this, rule, previousFilter);
                    }
                })
            } else {
                originalUpdateRuleFilter.call(this, rule, previousFilter);
            }
        }
        window.$.fn.queryBuilder.defaults({lang_code: 'main'});


        prepareFilters(() => {
            initQueryBuilderFilter();
        });
    })
</script>
<div>
    <div class="row">
        <button  class="filter-item" data-action="filter" data-name="posts" on:click={unsetAll}>
            <span class="fas fa-times fa-sm"></span>
            {Language.translate('Unset All')}
        </button>
    </div>
    {#if boolFilterList?.length > 0}
        <h5>{Language.translate('General Filters')}</h5>
        <ul>
            {#each boolFilterList as filter}
                <li class="checkbox">
                    <label class:active={selectedFilterList.includes(filter)}>
                    <input type="checkbox" checked={selectedFilterList.includes(filter)} on:change={(e) => handleGeneralFilterChecked(e, filter)} name="{filter}">
                        {Language.translate(filter, 'boolFilters', scope)}
                    </label>
                </li>
            {/each}
        </ul>
    {/if}
    <h5>{Language.translate('Advanced Filters')}</h5>
    <div class="row filter-action">
        <button  class="filter-item" data-action="filter" on:click={applyFilter}>
            <span class="fas fa-check fa-sm"></span>
            {Language.translate('Apply')}
        </button>
        <button  class="filter-item" data-action="filter"  on:click={resetFilter}>
            <span class="fas fa-times fa-sm"></span>
            {Language.translate('Unset')}
        </button>

    </div>
    <div class="query-builder" bind:this={queryBuilderElement}></div>
</div>

<style>
    .filter-item {
        border: 1px solid rgb(126 183 241);
        border-radius: 5px;
        background-color: rgba(126, 183, 241, 0.25);
        color: var(--primary-font-color);
        padding: 5px 10px;
        font-size: 13px;
        line-height: 1;
        margin-left:  5px;
    }
    .filter-action .filter-item {
        float: right
    }

    .filter-item:hover {
        border: 1px solid rgb(126 183 241);
        border-radius: 0 5px 5px 0;
        background-color: rgba(126, 183, 241, 0.1);
    }

    .filter-action{
        margin-bottom: 10px;
        margin-left:  0;
        margin-right:  0;
    }

    ul {
        padding-left: 20px;
        margin-bottom: 20px;
    }

    li label {
        color: var(--primary-font-color);
        padding-left: 0;
    }

    li label.active {
        color: #06c;
    }
</style>
