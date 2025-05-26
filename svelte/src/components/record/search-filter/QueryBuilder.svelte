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
    import {getSavedSearchStore} from "./stores/SavedSearch";
    import {getGeneralFilterStore} from './stores/GeneralFilter'
    import {Config} from "../../../utils/Config";

    export let scope: string;
    export let searchManager: any;
    export let createView: Function;
    export let parentWidth: number;
    export let uniqueKey: string = 'default';

    let filters: Array<any> = [];

    let queryBuilderElement: HTMLElement

    let model = new searchManager.collection.model();

    let advancedFilterChecked = false;

    let generalFilterOpened: boolean = false;

    let savedFiltersOpened: boolean = true;

    let editingSavedSearch: any = null;

    let oldAdvancedFilter: any = null;

    let showUnsetAll: boolean = false;

    let advancedFilterDisabled: boolean;

    let generalFilterStore = getGeneralFilterStore(uniqueKey);

    let savedSearchStore = getSavedSearchStore(scope, uniqueKey, {
        items: searchManager.savedSearchList || [],
        selectedItems: searchManager.getSavedFilters().map(v => v.id)
    });

    generalFilterStore.advancedFilterChecked.set(searchManager.isQueryBuilderApplied());

    const selectSavedSub = savedSearchStore.selectedSavedItemIds.subscribe(_ => {
        refreshShowUnsetAll();
    });

    const selectBoolSub = generalFilterStore.selectBoolFilters.subscribe(_ => {
        refreshShowUnsetAll();
    });

    const advancedFilterCheckedSub = generalFilterStore.advancedFilterChecked.subscribe((value) => {
        advancedFilterChecked = value;
        refreshShowUnsetAll();
    });

    function updateSearchManager(data: any) {
        searchManager.update(data);
    }

    function camelCaseToHyphen(str: string) {
        return str.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    }

    function hyphenToCamelCase(str: string): string {
        if (str === null || str === undefined) {
            return "";
        }
        return str.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
    }

    function underscoreToCamelCase(str: string) {
        if (str === null || str === undefined) {
            return "";
        }
        return str.replace(/[-_]([a-z])/g, (_, letter) => letter.toUpperCase());
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
        let rules = searchManager.getQueryBuilder() || [];
        if (typeof rules === 'object' && !rules.condition) {
            rules = [];
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
                label: `[ ${Language.translate('addAttribute')} ]`,
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
            uniqueKey: uniqueKey,
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
                {type: 'is_attribute_linked', nb_inputs: 0, apply_to: ['string']},
                {type: 'is_attribute_not_linked', nb_inputs: 0, apply_to: ['string']}
            ],
            rules: rules,
            filters: filters,
            plugins: {
                sortable: {
                    icon: 'ph ph-arrows-out-cardinal'
                },
            },
            icons: {
                error: 'ph ph-warning-circle',
                remove_rule: 'ph ph-x',
                remove_group: 'ph ph-x',
            },
            templates: {
                group: ({ group_id, level, conditions, icons, settings, translate, builder }) => `
                    <div id="${group_id}" class="rules-group-container">
                      <div class="rules-group-header">
                        <div class="rules-group-header-icons">
                            ${settings.display_errors ? `
                              <div class="error-container"><i class="${icons.error}"></i></div>
                            ` : ''}
                            ${level > 1 ? `
                                <button type="button" class="btn btn-danger outline rule-delete" data-delete="group">
                                  <i class="${icons.remove_group}"></i>
                                </button>
                            ` : ''}
                        </div>
                        <div class="btn-group float-end group-actions">
                          <button type="button" class="btn btn-sm btn-success" data-add="rule">
                            ${translate("add_rule")}
                          </button>
                          ${settings.allow_groups === -1 || settings.allow_groups >= level ? `
                            <button type="button" class="btn btn-sm btn-success" data-add="group">
                              ${translate("add_group")}
                            </button>
                          ` : ''}
                        </div>
                        <div class="btn-group group-conditions">
                          ${conditions.map(condition => `
                            <label class="btn btn-sm btn-primary">
                              <input type="radio" name="${group_id}_cond" value="${condition}"> ${translate("conditions", condition)}
                            </label>
                          `).join('\n')}
                        </div>
                      </div>
                      <div class=rules-group-body>
                        <div class=rules-list></div>
                      </div>
                    </div>
                `,
                rule: ({rule_id, icons, settings, translate, builder}) => `
                    <div id="${rule_id}" class="rule-container">
                      <div class="rule-header">
                        <div class="btn-group float-end rule-actions">
                          <button type="button" class="btn btn-danger outline rule-delete" data-delete="rule">
                            <i class="${icons.remove_rule}"></i>
                          </button>
                        </div>
                      </div>
                      ${settings.display_errors ? `
                        <div class="error-container"><i class="${icons.error}"></i></div>
                      ` : ''}
                      <div class="rule-container-group">
                        <div class="rule-filter-container"></div>
                        <div class="rule-operator-container"></div>
                        <div class="rule-value-container"></div>
                      </div>
                    </div>
                `
            }

        });

        model.trigger('afterInitQueryBuilder');
        $queryBuilder.on('rulesChanged.queryBuilder', async (e, rule) => {
            advancedFilterChecked = false;

            try {
                const rules = $queryBuilder.queryBuilder('getRules');
                if (rules) {
                    updateSearchManager({
                        queryBuilder: rules,
                        advanced: []
                    });
                    handleAdvancedFilterChecked(false);
                    if(rules.rules.length === 0) {
                        updateCollection();
                    }
                }

                await tick();
                $queryBuilder.find('.rule-filter-container select:not(.selectized)').selectize();
                $queryBuilder.find('.rule-operator-container select:not(.selectized)').selectize();
            } catch (err) {
            }
            model.trigger('rulesChanged', rule);
        });

        $queryBuilder.on('afterUpdateRuleOperator.queryBuilder', (e, rule) => {
            model.trigger('afterUpdateRuleOperator', rule);
        });

        $queryBuilder.on('beforeUpdateRuleFilter.queryBuilder',function(e, rule, previousFilter) {
            let qb = window.$(this)[0].queryBuilder;
            if(qb.settings.uniqueKey !== uniqueKey) {
                e.preventDefault();
            }

            if (rule.filter && rule.filter.id === 'emptyAttributeRule') {
                e.preventDefault();
                addAttributeFilter((pushed, newFilters) => {
                    if (pushed) {
                        qb.setFilters(filters);
                    }
                    if (newFilters) {
                        rule.filter = newFilters[0];
                        if (newFilters.length > 1) {
                            for (const newFilter of newFilters) {
                                if (newFilter.id === rule.filter.id) {
                                    continue;
                                }

                                let r = qb.addRule(rule.parent);
                                r.filter = newFilter;
                            }
                        }
                    }
                    if (!rule.filter || rule.filter.id === 'emptyAttributeRule') {
                        rule.filter = previousFilter;
                        previousFilter = null
                        qb.updateRuleFilter(rule, previousFilter);
                        rule.$el.find('.rule-filter-container select')[0].selectize.setValue(rule.filter.id);
                    }else{
                        qb.updateRuleFilter(rule, previousFilter);
                    }
                })
            }else{
                model.trigger('beforeUpdateRuleFilter', rule);
            }
        });

        $queryBuilder.on('afterUpdateRuleFilter.queryBuilder', async (e, rule) => {
            await tick();
            if (rule.$el) {
                rule.$el.find('.rule-operator-container select:not(.selectized)').selectize();
            }

            model.trigger('afterUpdateRuleFilter', rule);
        });

        $queryBuilder.on('afterSetRules.queryBuilder', (e, rule) => {
            model.trigger('afterInitQueryBuilder');
        });

        $queryBuilder.on('afterAddGroup.queryBuilder', (e, rule) => {
            model.trigger('afterAddGroup', rule);
        });

        $queryBuilder.on('afterAddRule.queryBuilder', async (e, rule) => {
            await tick();
            if (rule.$el) {
                rule.$el.find('.rule-filter-container select:not(.selectized)').selectize();
            }

            model.trigger('afterAddRule', rule);
        });
    }

    function getFieldOrAttributeId(field: string) {
        let id = field;
        let parts = field.split('_')
        if (parts.length >= 2 && parts[0] === 'attr') {
            id = parts[1];
            const endings = ["From", "To", "UnitId", "Id"];
            for (const ending of endings) {
                if (id.endsWith(ending)) {
                    id = id.slice(0, -ending.length);
                    break;
                }
            }
        }
        return id;
    }

    function prepareFilters(callback: Function) {

        filters = filters.filter(item => item.id.startsWith('attr_'));

        let promiseList: Promise[] = [];

        Object.entries(Metadata.get(['entityDefs', scope, 'fields'])).forEach(([field, fieldDefs]) => {
            if (fieldDefs.filterDisabled || fieldDefs.virtualField) {
                return;
            }

            const fieldType = camelCaseToHyphen(fieldDefs.type);
            const view = fieldDefs.view || Metadata.get(['fields', fieldDefs.type, 'view'])  || `views/fields/${fieldType}`;
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
                    let filter = view.createQueryBuilderFilter(fieldDefs.type);
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
                let attributesIds: string[] = [];
                getRulesIds(rules.rules).forEach(id => {
                    if (id.startsWith('attr_')) {
                        attributesIds.push(getFieldOrAttributeId(id));
                    }
                });

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
                            cleanUpSavedRule((fieldId: string) => {
                                if (fieldId.startsWith('attr_')) {
                                    return attrs.list.find(v => v.id === getFieldOrAttributeId(fieldId))
                                } else {
                                    return true;
                                }
                            });

                            if (attrs.list.length) {
                                let resolved = []
                                attrs.list.forEach(attribute => {
                                    pushAttributeFilter(attribute, (pushed, filter) => {
                                        resolved.push(attribute.id);
                                        if (resolved.length === attrs.list.length) {
                                            resolve();
                                        }
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

            const $queryBuilder = window.$(queryBuilderElement);
            $queryBuilder.find('.rule-filter-container select').selectize();
            $queryBuilder.find('.rule-operator-container select').selectize();
        });
    }


    function resetFilter() {
        if (advancedFilterDisabled) {
            return;
        }
        advancedFilterChecked = false;
        handleAdvancedFilterChecked(false)
        updateSearchManager({
            queryBuilder: [],
            advanced: []
        });
        window.$(queryBuilderElement).queryBuilder('setRules', []);
        updateCollection();
    }

    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.fetchCollection();
    }

    function pushAttributeFilter(attribute: any, callback: Function) {
        let promises: Promise[] = []
        let filterChanged = false;
        const fieldType = camelCaseToHyphen(attribute.type);
        const name = `attr_${attribute.id}`;
        const label = attribute.name;
        const params: any = {
            attribute
        }

        if (['extensibleEnum', 'extensibleMultiEnum'].includes(attribute.type)) {
            params['extensibleEnumId'] = attribute.extensibleEnumId;
        }

        let createFieldView = (name: string, fieldType: string, label: string, params = {}, order = 0) => {
            return new Promise((resolve) => {
                const view = Metadata.get(['fields', attribute.type, 'view']) ?? `views/fields/${fieldType}`;
                let exitingFilter = filters.find(f => f.id === name);
                if(exitingFilter) {
                    resolve(exitingFilter);
                }else{
                    createView(name, view, {
                        name: name,
                        model: model,
                        defs: {
                            name: name,
                            params: params
                        },
                    }, view => {
                        let filter = view.createQueryBuilderFilter(attribute.type);
                        if (filter) {
                            filter.label = label;
                            filter.optgroup = Language.translate('Attributes');
                            filter.order = order;
                            filter.operators.unshift('is_attribute_not_linked');
                            filter.operators.unshift('is_attribute_linked');
                            if (!filters.find(f => f.id === name)) {
                                filters.push(filter);
                                filterChanged = true;
                                resolve(filter)
                            }
                        }
                    });
                }
            });
        };

        if (['rangeInt', 'rangeFloat'].includes(attribute.type)) {
            let type = attribute.type === 'rangeInt' ? 'int' : 'float';
            ['From', 'To'].forEach((v, key) => {
                promises.push(createFieldView(name + v, type, label + ' ' + Language.translate(v), params, key));
            })
        } else if (attribute.isMultilang) {
            let languages: string[] = Config.get('inputLanguageList') ?? [];
            languages = ['main', ...languages];
            let i = 0;
            for (const language of languages) {
                if(language === Config.get('mainLanguage')) {
                    continue;
                }
                let currentLabel = label;
                let currentName = name + '_' + underscoreToCamelCase(language.toLowerCase());
                if (language !== 'main') {
                    currentLabel = currentLabel + ' / ' + language
                }
                promises.push(createFieldView(currentName, fieldType, currentLabel, params, i));
                i++;
            }

        } else {
            promises.push(createFieldView(name, fieldType, label, params));
        }

        if (attribute.measureId) {

            promises.push(createFieldView(name + 'UnitId', 'unit-link', label + ' ' + Language.translate('Unit'), {
                ...params,
                type: 'unit',
                measureId: attribute.measureId
            }, 2));
        }

        Promise.all(promises).then(newFilters => {
            newFilters.sort((a, b) => a.order - b.order);
            window.currentFilters = filters;
            if(attribute.isMultilang) {
                callback(filterChanged, [newFilters[0]]);
            }else{
                callback(filterChanged, newFilters);
            }
        })

    }

    function hasAttribute() {
        return (Acl.check('Attribute', 'read') && scope === 'Product' && Metadata.get(['scopes', 'Product', 'module']) === 'Pim')
            || Metadata.get(['scopes', scope, 'hasAttribute']);
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
            allowSelectAllResult: false,
            boolFilterList: ['onlyForEntity'],
            boolFilterData: {
                onlyForEntity: scope
            }
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
        if (!showUnsetAll) {
            return;
        }
        searchManager.update({
            bool: {},
            savedFilters: [],
            queryBuilderApplied: false,
            advanced: []
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
            queryBuilderApplied: advancedFilterChecked
        });

        if (refresh) {
            updateCollection();
        }

        refreshShowUnsetAll()
    }

    async function saveSaveSearch(data, id = null): Promise<void> {
        Notifier.notify(Language.translate('pleaseWait', 'messages'));
        savedSearchStore.saveSavedSearch(data, id).then(data => {
            if (id !== null) {
                cancelEditSearchQuery()
            }
            Notifier.notify(Language.translate('Done'), 'success');
        }).catch(e => {
            console.error('Error on saving saveSearch', e);
            Notifier.notify(false)
        })
    }

    function saveFilter() {
        if (advancedFilterDisabled) {
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
        oldAdvancedFilter = oldAdvancedFilter ?? searchManager.getQueryBuilder();
        searchManager.update({queryBuilder: item.data});
        prepareFilters(() => {
            const $queryBuilder = window.$(queryBuilderElement)
            try {
                $queryBuilder.queryBuilder('destroy');

                initQueryBuilderFilter();
                editingSavedSearch = item;
            } catch (e) {
                console.error(e);
                Notifier.notify(Language.translate('theSavedFilterMightBeCorrupt', 'messages'), 'error')
                $queryBuilder.queryBuilder('destroy');
                searchManager.update({
                    queryBuilder: oldAdvancedFilter
                })
                initQueryBuilderFilter();
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

        if (typeof rules === 'object' && rules.condition) {
            advancedFilterDisabled = isRuleEmpty(rules);
        }

        generalFilterStore.advancedFilterDisabled.set(advancedFilterDisabled);

        if (advancedFilterDisabled) {
            generalFilterStore.advancedFilterChecked.set(false);
            advancedFilterChecked = false;
        }
    }

    // return true the filter have been updates
    function cleanUpSavedRule(exists: Function): boolean {
        // we clean up to remove  fields that do not exist anymore
        let hasChanged = false;
        let cleanUpRule = (rule: Rule) => {
            if (rule.rules) {
                let newRules: Rule[] = [];
                for (const rulesKey in rule.rules) {
                    if (rule.rules[rulesKey].id) {
                        if (!exists(rule.rules[rulesKey].id)) {
                            hasChanged = true;
                            newRules = rule.rules.filter(v => v.id !== rule.rules[rulesKey].id);
                        }
                    }
                    if (rule.rules[rulesKey] && rule.rules[rulesKey].rules) {
                        cleanUpRule(rule.rules[rulesKey]);
                    }
                }
                if (newRules.length !== rule.rules.length) {
                    rule.rules = newRules;
                }
            }
        }

        let rule = searchManager.getQueryBuilder();
        cleanUpRule(rule);
        if (hasChanged) {
            searchManager.update({queryBuilder: rule})
        }

        return hasChanged
    }

    function refreshShowUnsetAll() {
        refreshAdvancedFilterDisabled();
        showUnsetAll = searchManager.isFilterSet();
    }

    function isRuleEmpty(rule: Rule): boolean {
        if (rule.operator) {
            return false;
        }

        if (!rule.rules) {
            return true;
        }

        return rule.rules.length === 0;
    }

    function collapseAll(e: MouseEvent) {
        savedFiltersOpened = false;
        generalFilterOpened = false;
    }

    function expandAll(e: MouseEvent) {
        savedFiltersOpened = true;
        generalFilterOpened = true;
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


        // we override only if it is a new page
        if(!window.$.fn.queryBuilder.prototype.overridden) {
            window.$.extend(window.$.fn.queryBuilder.prototype, {
                overridden: true
            });
            let originalUpdateRuleFilter = window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter;

            window.$.fn.queryBuilder.constructor.prototype.updateRuleFilter = function (rule, previousFilter) {
                let e =  this.trigger('beforeUpdateRuleFilter', rule, previousFilter);
                if (e.isDefaultPrevented()) {
                    return null;
                }
                originalUpdateRuleFilter.call(this, rule, previousFilter);
            }

            let originalGetFilterById = window.$.fn.queryBuilder.constructor.prototype.getFilterById;
            window.$.fn.queryBuilder.constructor.prototype.getFilterById = function (id, doThrow) {
                if (id === '') {
                    return null;
                }

                return originalGetFilterById.call(this, id, doThrow);
            };
        }


        advancedFilterChecked = searchManager.isQueryBuilderApplied();

        // show unset all
        refreshShowUnsetAll();
        searchManager.collection.on('filter-state:changed', (value) => showUnsetAll = !!value);

        prepareFilters(() => {
            initQueryBuilderFilter();
        });

        return () => {
            searchManager.collection.off('filter-state:changed');
            selectBoolSub();
            selectSavedSub();
            advancedFilterCheckedSub();
        }
    })
</script>

<div class="query-builder-container">
    <div class="filters-top-buttons">
        <div class="btn-group">
            <button class="btn btn-sm btn-default filter-button" data-action="collapseAll"
                    title={Language.translate('collapseAll')} on:click={collapseAll}>
                <i class="ph ph-caret-line-up"></i>
            </button>
            <button class="btn btn-sm btn-default filter-button" data-action="expandAll"
                    title={Language.translate('expandAll')} on:click={expandAll}>
                <i class="ph ph-caret-line-down"></i>
            </button>
        </div>
        {#if showUnsetAll}
            <button class="btn btn-sm btn-default filter-button" data-action="filter" on:click={unsetAll}>
                <i class="ph ph-x"></i>
                {Language.translate('Unset All')}
            </button>
        {/if}
    </div>
    <GeneralFilter scope={scope} searchManager={searchManager} uniqueKey={uniqueKey} bind:opened={generalFilterOpened} />
    {#if Acl.check('SavedSearch', 'read')}
        <SavedSearch
                scope={scope}
                searchManager={searchManager}
                editingItem={editingSavedSearch}
                rename={renameSaveSearch}
                remove={removeSaveSearch}
                edit={editSaveSearchQuery}
                cancel={cancelEditSearchQuery}
                uniqueKey={uniqueKey}
                hideRowAction={uniqueKey.includes('dialog')}
                bind:opened={savedFiltersOpened}
        />
    {/if}

    <div class="advanced-filters">
        <h5>
            <input type="checkbox" disabled={advancedFilterDisabled} bind:checked={advancedFilterChecked}
                   on:change={(e) => handleAdvancedFilterChecked()}>
            <span>{Language.translate('Apply Advanced Filter')}</span></h5>
        <div class="query-builder" bind:this={queryBuilderElement}></div>
        {#if !advancedFilterDisabled}
            <div class="filter-action">
                <button class="btn btn-sm btn-default filter-button" on:click={resetFilter}>
                    <i class="ph ph-x"></i>
                    {Language.translate('Unset')}
                </button>
                {#if Acl.check('SavedSearch', 'create') && !uniqueKey.includes('dialog') }
                    <button class="btn btn-sm btn-success filter-button" on:click={saveFilter}>
                        <i class="ph ph-floppy-disk-back"></i>
                        {Language.translate('Save')}
                    </button>
                {/if}
            </div>
        {/if}
    </div>
</div>

<style>
    .query-builder-container :global(.checkboxes-filter) {
        margin-bottom: 10px;
    }

    .btn-sm {
        padding: 4px 8px;
    }

    .btn-sm i {
        font-size: 14px;
    }

    .filters-top-buttons {
        margin-bottom: 5px;
        min-height: 25px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
    }

    .filters-top-buttons .btn-group .filter-button {
        border-radius: 0;
    }

    .filters-top-buttons .btn-group .filter-button:first-child {
        border-top-left-radius: 4px;
        border-bottom-left-radius: 4px;
    }

    .filters-top-buttons .btn-group .filter-button:last-child {
        border-top-right-radius: 4px;
        border-bottom-right-radius: 4px;
    }

    .filter-action {
        background-color: var(--sidebar-color);
        margin-top: 10px;
        padding-top: 10px;
        padding-bottom: 10px;
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1;
        display: flex;
        justify-content: space-between;
    }

    .advanced-filters {
        margin-top: 20px;
        position: relative;
    }

    .advanced-filters h5 {
        display: flex;
        align-items: center;
        margin: 0 0 15px 0;
    }

    .advanced-filters h5 input[type="checkbox"] {
        margin-top: 0;
        margin-right: 10px;
        margin-left: 3px;
    }

    :global(.query-builder .input-group-btn .btn) {
        height: 32px;
        padding: 0;
    }

    :global(.query-builder .btn.rule-delete) {
        border: 0;
        padding: 0;
        background-color: transparent;
        float: right;
        margin-left: auto;
    }

    .filter-button {
        border-radius: 4px;
    }

    .query-builder :global(.rules-group-header) {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 10px 5px;
    }

    .query-builder :global(.rules-group-header .drag-handle) {
        order: -1;
    }

    .query-builder :global(.rules-group-header .group-actions) {
        order: 2;
    }

    .query-builder :global(.rules-group-header .group-conditions) {
        order: 1;
    }

    .query-builder :global(.rules-group-header .rules-group-header-icons) {
        flex: 1 1 80%;
        display: flex;
        justify-content: space-between;
        order: 0;
    }

    .query-builder :global(> .rules-group-container > .rules-group-header .rules-group-header-icons) {
        display: none;
    }

    .query-builder :global(.rule-container-group) {
        display: flex;
        flex-wrap: wrap;
        column-gap: 10px;
        min-width: 100%;
        margin-left: 0;
        margin-right: -5px;
        container-type: inline-size;
    }

    .query-builder :global(.rule-container-group .rule-operator-container),
    .query-builder :global(.rule-container-group .rule-filter-container),
    .query-builder :global(.rule-container-group .rule-value-container) {
        flex-basis: 100%;
    }

    @container (min-width: 400px) {
        .query-builder :global(.rule-container-group .rule-filter-container) {
            flex: 1 1 0;
        }

        .query-builder :global(.rule-container-group .rule-operator-container) {
            flex-basis: 170px;
            flex-grow: 0;
            flex-shrink: 0;
        }
    }
</style>
