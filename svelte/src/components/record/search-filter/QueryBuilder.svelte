<script lang="ts">
    import {onMount, tick} from "svelte";
    import {Metadata} from "../../../utils/Metadata";
    import {Storage} from "../../../utils/Storage";
    import Rule from "./interfaces/Rule";
    import {Acl} from "../../../utils/Acl";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import {UserData} from "../../../utils/UserData";
    import SavedSearch from "./SavedSearch.svelte";
    import GeneralFilter from "./GeneralFilter.svelte";
    import {savedSearchStore} from "./stores/SavedSearch";
    import {generalFilterStore} from "./stores/GeneralFilter";

    export let scope: string;
    export let searchManager: any;
    export let createView: Function;
    export let parentWidth: number;

    let filters: Array<any> = [];

    let queryBuilderElement: HTMLElement

    let model = new searchManager.collection.model();

    let advancedFilterChecked = false;

    let editingSavedSearch: any = null;

    let oldAdvancedFilter: any = null;

    let showUnsetAll: boolean = false;

    let advancedFilterDisabled: boolean;

    $: {
        updateStyle(parentWidth);
    }

    generalFilterStore.advancedFilterChecked.set(searchManager.isQueryBuilderApplied());

    const selectSavedSub = savedSearchStore.selectedSavedItemIds.subscribe(_ => {
        refreshShowUnsetAll();
    });

   const selectBoolSub = generalFilterStore.selectBoolFilters.subscribe(_ => {
        refreshShowUnsetAll();
    });

   const advancedFilterCheckedSub =  generalFilterStore.advancedFilterChecked.subscribe((value) => {
       advancedFilterChecked = value;
       refreshShowUnsetAll();
   });

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
                if (element.style.display === 'none') {
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

    function updateSearchManager(data: any) {
        searchManager.set({...searchManager.get(), ...data});
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

    function getRulesWithBadRuleRemoved(rules: Rule[]): Rule[] {
            let newRules: Rule[] = rules;
            rules.forEach((rule, key) => {
                if (rule.rules) {
                    rules[key].rules  = getRulesWithBadRuleRemoved(rules[key].rules)
                } else if (rule.id ) {
                    if (!filters.find(f => f.id === rule.id)) {
                        newRules = rules.filter(r => r.id !== rule.id)
                    }
                }
            });
        return newRules;
    }

    function initQueryBuilderFilter() {

        const $queryBuilder = window.$(queryBuilderElement)
        let rules = searchManager.getQueryBuilder() || [];
        if(typeof rules === 'object' && !rules.condition) {
            rules = [];
        }
        if(rules['rules']) {
            rules['rules'] = getRulesWithBadRuleRemoved(rules['rules']);
        }

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

        if (hasAttribute()) {
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
                {type: 'is_not_linked', nb_inputs: 0, apply_to: ['string']}
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
            advancedFilterChecked = false;
            setTimeout(() => {
                updateStyle(parentWidth);
            }, 250)

            try {
                const rules = $queryBuilder.queryBuilder('getRules');
                if (rules) {
                    updateSearchManager({
                        queryBuilder: rules
                    });
                    handleAdvancedFilterChecked(false);
                }
            } catch (err) {
            }
            model.trigger('rulesChanged', rule);
        });

        $queryBuilder.on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleOperator', rule);
        });

        $queryBuilder.on('beforeUpdateRuleFilter.queryBuilder', (e, rule) => {
            model.trigger('beforeUpdateRuleFilter', rule);
        });

        $queryBuilder.on('afterSetRules.queryBuilder', (e, rule) => {
            model.trigger('afterInitQueryBuilder');
        });

        $queryBuilder.on('afterAddGroup.queryBuilder', (e, rule) => {
            model.trigger('afterAddGroup', rule);
            updateStyle(parentWidth);
        });

        $queryBuilder.on('afterAddRule.queryBuilder', (e, rule) => {
            setTimeout(() => {
                updateStyle(parentWidth);
            }, 250)
            model.trigger('afterAddRule', rule);
        });
    }

    function prepareFilters(callback: Function) {

        filters = filters.filter(item => item.id.startsWith('attr_'));

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


        const rules = searchManager.getQueryBuilder();

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
                            // we clean up the rules to remove attribute rule if attribute does not exist anymore
                           let hasChanged = cleanUpSavedRule((fieldId) => {
                                let parts = fieldId.split('_');
                                if (parts.length === 2 && parts[0] === 'attr') {
                                   return !!attrs.list.find(v => v.id ===  parts[1]);
                                }else{
                                    return true;
                                }
                            });

                           if(hasChanged) {
                               updateCollection();
                           }
                            if (attrs.list.length) {
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


    function resetFilter() {
        if(advancedFilterDisabled) {
            return;
        }
       advancedFilterChecked = false;
       handleAdvancedFilterChecked(false)
        updateSearchManager({
            queryBuilder: []
        });
        window.$(queryBuilderElement).queryBuilder('setRules', []);
        updateCollection();
    }

    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.collection.reset();

        searchManager.collection.where = searchManager.getWhere();
        searchManager.collection.abortLastFetch();
        searchManager.collection.fetch().then(() => window.Backbone.trigger('after:search', searchManager.collection));
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
        return Acl.check('Attribute', 'read') && Metadata.get(['scopes', scope, 'hasAttribute']) || scope === 'Product';
    }

    function addAttributeFilter(callback) {
        const attributeScope = 'Attribute';
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

    function unsetAll() {
        if(!showUnsetAll) {
            return;
        }
        searchManager.update({
            bool: {},
            savedFilters: [],
            queryBuilderApplied: false
        });
        advancedFilterChecked = false;
        handleAdvancedFilterChecked(false);
        savedSearchStore.selectedSavedItemIds.set([]);
        generalFilterStore.selectBoolFilters.set([]);
        refreshShowUnsetAll();
        updateCollection();
        window.dispatchEvent(new CustomEvent('filter:unset-all'));
    }

    function handleAdvancedFilterChecked(refresh = true) {
        if (advancedFilterChecked) {
            let validation = window.$(queryBuilderElement).queryBuilder('validate');
            if (!validation) {
                Notifier.notify(Language.translate('youHaveErrorsInFilter', 'messages'), 'error');
                advancedFilterChecked = false;
                return;
            }
        }

        generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);

        updateSearchManager({
            queryBuilderApplied: advancedFilterChecked ? 'apply' : false
        });

        if(refresh) {
            updateCollection();
        }

        refreshShowUnsetAll()
    }

    async function saveSaveSearch(data, id = null): Promise<void> {
        Notifier.notify(Language.translate('pleaseWait', 'messages'));
        savedSearchStore.saveSavedSearch(data, id).then( data =>{
            if(id !== null) {
                cancelEditSearchQuery()
            }
            Notifier.notify(Language.translate('Done'), 'success');
        }).catch(e => {
            console.error('Error on saving saveSearch', e);
            Notifier.notify(false)
        })
    }

    function saveFilter() {
        if(advancedFilterDisabled) {
            return;
        }
        let validation = window.$(queryBuilderElement).queryBuilder('validate');
        if (!validation) {
            Notifier.notify(Language.translate('youHaveErrorsInFilter', 'messages'), 'error');
            return;
        }

        if (editingSavedSearch !== null) {
            saveSaveSearch({
                data: window.$(queryBuilderElement).queryBuilder('getRules')
            }, editingSavedSearch.id);
            return;
        }

        createView('savePreset', 'views/modals/save-filters', {}, function (view) {
            view.render();
            view.listenToOnce(view, 'save', (params) => {
                saveSaveSearch({
                    entityType: scope,
                    name: params.name,
                    data: searchManager.getQueryBuilder(),
                    isPublic: params.isPublic
                });
                view.close();
            });
        });
    }

    function renameSaveSearch(item) {
        createView('savePreset', 'views/modals/save-filters', {
            name: item.name,
            isPublic: item.isPublic
        }, function (view) {
            view.render();
            view.listenToOnce(view, 'save', (params) => {
                saveSaveSearch({
                    name: params.name,
                    isPublic: params.isPublic
                }, item.id)
                view.close();
            });
        });
    }

    async function removeSaveSearch(item) {
        const userData = UserData.get();
        if (!userData) {
            return;
        }
        Notifier.notify(Language.translate('pleaseWait', 'messages'));
        savedSearchStore.removeSavedSearch(item.id).then(_ => {
            Notifier.notify(Language.translate('Done'), 'success');
        }).catch(e => {
            console.error('Error on deleting saveSearch', e);
            Notifier.notify(false)
        });
    }

    function editSaveSearchQuery(item) {
        prepareFilters(() => {
            const $queryBuilder = window.$(queryBuilderElement)
            try {
                oldAdvancedFilter = oldAdvancedFilter ?? searchManager.getQueryBuilder();
                $queryBuilder.queryBuilder('setFilters', filters)
                $queryBuilder.queryBuilder('setRules', item.data)
                editingSavedSearch = item;
            } catch (e) {
                console.error(e);
                Notifier.notify(Language.translate('theSavedFilterMightBeCorrupt', 'messages'), 'error')
                $queryBuilder.queryBuilder('setRules', searchManager.getQueryBuilder());
            }
        });
    }

    function cancelEditSearchQuery() {
        const $queryBuilder = window.$(queryBuilderElement)
        $queryBuilder.queryBuilder('setRules', oldAdvancedFilter ?? []);
        oldAdvancedFilter = null;
        editingSavedSearch = null;
    }

    function refreshAdvancedFilterDisabled() {
        let rules = searchManager.getQueryBuilder();
        advancedFilterDisabled = true;

        if(typeof rules === 'object' && rules.condition) {
            advancedFilterDisabled = isRuleEmpty(rules);
        }

        generalFilterStore.advancedFilterDisabled.set(advancedFilterDisabled);

        if(advancedFilterDisabled) {
            generalFilterStore.advancedFilterChecked.set(false);
            advancedFilterChecked = false;
        }
    }

    // return true the filter have been updates
    function cleanUpSavedRule( exists: Function): boolean{
        // we clean up to remove  fields that do not exist anymore
        let hasChanged = false;
       let  cleanUpRule = (rule: Rule) => {
           if(rule.rules) {
               for (const rulesKey in rule.rules) {
                   if(rule.rules[rulesKey].id) {
                       if(!exists(rule)){
                           hasChanged = true;
                           rule.rules = rule.rules.filter(v => v.id !== rule.rules[rulesKey].id);
                       }
                   }

                   if(rule.rules[rulesKey] && rule.rules[rulesKey].rules) {
                      cleanUpRule(rule.rules[rulesKey]);
                   }
               }
           }
        }

        let rule = searchManager.getQueryBuilder();
       cleanUpRule(rule);
       if(hasChanged) {
           searchManager.update({queryBuilder: rule})
       }

       return hasChanged
    }
    function refreshShowUnsetAll() {
      refreshAdvancedFilterDisabled();
       setTimeout(() => {
           showUnsetAll = searchManager.isQueryBuilderApplied() || searchManager.getSavedFilters().length > 0
           let bool = searchManager.getBool();
           for (const boolKey in bool) {
               if(bool[boolKey]){
                   showUnsetAll = true;
                   break;
               }
           }
       }, 100)
    }

    function isRuleEmpty(rule: Rule): boolean {
        if(rule.operator) {
            return  false;
        }

        if(!rule.rules) {
            return true;
        }

        return rule.rules.length === 0;
    }

    onMount(() => {
        // load where params
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('where')) {
            const where = JSON.parse(urlParams.get('where'));
            if (where) {
                Storage.set('queryBuilderRules', scope, where);
                window.history.replaceState({}, document.title, window.location.origin + '#' + scope);
                updateCollection()
            }
        }

        // set translates
        window.$.fn.queryBuilder.regional['main'] = Language.getData().Global.queryBuilderFilter;
        window.$.fn.queryBuilder.defaults({lang_code: 'main'});

        // override updateRuleFilter
        let originalUpdateRuleFilter = window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter;
        window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter = function (rule, previousFilter) {
            this.trigger('beforeUpdateRuleFilter', rule);
            if (rule.filter && rule.filter.id === 'emptyAttributeRule') {
                addAttributeFilter((pushed, filter) => {
                    if (pushed) {
                        this.setFilters(filters)
                        rule.filter = filter;
                        originalUpdateRuleFilter.call(this, rule, previousFilter);
                    } else {
                        if (rule.filter.id === 'emptyAttributeRule') {
                            rule.filter = previousFilter;
                        }
                        originalUpdateRuleFilter.call(this, rule, previousFilter);
                    }
                })
            } else {
                originalUpdateRuleFilter.call(this, rule, previousFilter);
            }
        }


        advancedFilterChecked = searchManager.isQueryBuilderApplied();

        // show unset all
       refreshShowUnsetAll();

        prepareFilters(() => {
            initQueryBuilderFilter();
        });

        return () => {
            selectBoolSub();
            selectSavedSub();
            advancedFilterCheckedSub();
        }
    })
</script>

<div class="query-builder-container">
    <div>

        <button class="filter-item" data-action="filter"  class:disabled={!showUnsetAll} on:click={unsetAll}>
            <i class="ph ph-x"></i>
            {Language.translate('Unset All')}
        </button>
    </div>
    <GeneralFilter scope={scope} searchManager={searchManager} />
    {#if Acl.check('SavedSearch', 'read')}
        <SavedSearch
                scope={scope}
                searchManager={searchManager}
                editingItem={editingSavedSearch}
                rename={renameSaveSearch}
                remove={removeSaveSearch}
                edit={editSaveSearchQuery}
                cancel={cancelEditSearchQuery}
        />
    {/if}

    <div class="advanced-filters">
        <h5>
            <input type="checkbox" disabled={advancedFilterDisabled} bind:checked={advancedFilterChecked} on:change={(e) => handleAdvancedFilterChecked()}>
            <span>{Language.translate('Advanced Filter')}</span></h5>
        <div class="row filter-action">
            <button class="filter-item" class:disabled={advancedFilterDisabled} on:click={resetFilter}>
                <i class="ph ph-x"></i>
                {Language.translate('Unset')}
            </button>
            {#if Acl.check('SavedSearch', 'create')}
                <button class="filter-item save" class:disabled={advancedFilterDisabled} on:click={saveFilter}>
                    <i class="ph ph-floppy-disk-back"></i>
                    {Language.translate('Save')}
                </button>
            {/if}
        </div>
        <div class="query-builder" bind:this={queryBuilderElement}></div>
    </div>
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
        margin-right: 5px;
    }

    .filter-item:hover {
        border: 1px solid rgb(126 183 241);
        background-color: rgba(126, 183, 241, 0.1);
    }

    .filter-item.save {
        background-color: #85b75f40;
        border-color: #85b75f;
    }

    .filter-item.save:hover {
        background-color: #85b75f12;
    }

    .filter-action {
        margin-bottom: 10px;
        margin-left: 0;
        margin-right: 0;
    }

    .advanced-filters h5 {
        display: flex;
        align-items: center;
    }

    .advanced-filters h5 input[type="checkbox"] {
        margin-top: 0;
        margin-right: 10px;
    }
    :global(.query-builder .input-group-btn .btn) {
        height: 33px;
        padding: 0;
    }

    .filter-item.save.disabled, button.disabled,  button.disabled:hover{
        background-color: #eee;
        border-color: #eee;
        cursor: not-allowed;
    }
</style>
