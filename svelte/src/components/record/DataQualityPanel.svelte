<script lang="ts">
    import {createEventDispatcher, onDestroy, onMount, tick} from "svelte";
    import { Metadata } from '$lib/core/metadata';
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {Language} from "../../utils/Language";
    import ContentFilter from "./ContentFilter.svelte";
    import {Storage} from "../../utils/Storage";
    import {Notifier} from "../../utils/Notifier";
    import {Acl} from "../../utils/Acl";

    const dispatch = createEventDispatcher();

    export let scope: string;
    export let id: string;
    export let fetchModel: Function


    let qualityCheckSelect;
    let qualityChecksList = [];

    let activeItem = null
    let loading = false
    let data = null
    let selectedFilters = Storage.get('qualityCheckRuleFilters', scope) || []
    let filteredRules = []
    let highlightedCheckId = null

    $: {
        const reelFilers = selectedFilters.length === 0 ? ['passed', 'failed'] : selectedFilters
        filteredRules = (data?.rules || []).filter(rule => reelFilers.includes(rule.status))
    }

    function onFilterChange(evt, value) {
        selectedFilters = value
    }

    function getValueStyle(value) {
        let backgroundColor = '#FFD6C9';
        if (value === -1) {
            backgroundColor = '#CCCCCC';
        }
        if (value > 0) {
            backgroundColor = '#FFE7D1';
        }
        if (value > 24) {
            backgroundColor = '#FEFFD6';
        }
        if (value > 49) {
            backgroundColor = '#FFF8B8';
        }
        if (value > 74) {
            backgroundColor = '#E0FFCC';
        }
        if (value === 100) {
            backgroundColor = '#CAF2C2';
        }

        let data = {
            cursor: 'pointer',
            'font-weight': 'normal',
            'background-color': backgroundColor,
            color: Utils.getFontColor(backgroundColor),
            border: Utils.getBorder(backgroundColor),
            padding: '4px 10px',
            fontSize: '100%'
        };

        if (value > 24) {
            data.display = 'block';
            data.width = `${value}%`;
        }

        return Object.entries(data).map(([k, v]) => `${k}: ${v}`).join('; ')
    }

    function getStatusStyle(status) {
        const backgroundColor = status === 'passed' ? '#CAF2C2' : (status === 'failed' ? '#FFD6C9' : '#CCCCCC');
        return `background-color: ${backgroundColor};`
    }

    async function loadQualityCheckData(reload = false) {
        loading = true
        if (!reload) {
            data = null
        }

        const resp = await Utils.getRequest('/QualityCheck/action/getEntityData', {
            entityName: scope,
            entityId: id,
            qualityCheckId: activeItem
        })

        if (resp.status === 200) {
            data = await resp.json()
        } else {
            data = null
            Notifier.notify('Error occurred', 'error')
        }

        loading = false
    }

    async function recalculateCheck() {
        if (!Acl.check(scope, 'edit')) {
            return;
        }

        Notifier.notify('Please wait...')
        const resp = await Utils.postRequest('/QualityCheck/action/recalculate', {
            entityName: scope,
            entityId: id,
            fieldName: qualityChecksList.find(item => item.value === activeItem)?.field,
        })

        if (resp.status === 200) {
            Notifier.notify('Done', 'success')
            fetchModel()
            loadQualityCheckData(true)
        } else {
            Notifier.notify('Error occurred', 'error')
        }
    }

    function onCheckRecalculated(evt) {
        if (evt.detail.field === qualityChecksList.find(item => item.value === activeItem)?.field) {
            loadQualityCheckData()
        }
    }

    function onShowDetails(evt) {
        const item = qualityChecksList.find(item => item.field === evt.detail.field)
        if (item) {
            activeItem = item.value
            qualityCheckSelect.selectize.setValue(activeItem)
            dispatch('show')
        }
    }

    function highlightCheck() {
        const el = document.querySelector(`.quality-check-highlighter[data-quality-check-id="${activeItem}"]`)
        if (el) {
            el.click()
        }
    }

    function onCheckHighlighted(evt) {
        highlightedCheckId = evt.detail.checkId
    }

    onMount(() => {
        const forbiddenFields = Acl.getScopeForbiddenFieldList(scope, 'read') || [];

        Object.entries(Metadata.get(['entityDefs', scope, 'fields'])).forEach(([field, defs]) => {
            if (defs.dataQualityCheck) {
                let text;
                Object.entries(Config.get('referenceData').QualityCheck).forEach(([key, check]) => {
                    if (check.id === defs.qualityCheckId) {
                        text = check.name;
                    }
                });

                if (!forbiddenFields.includes(field)) {
                    qualityChecksList.push({
                        value: defs.qualityCheckId,
                        text: text,
                        field: field,
                    });
                }
            }
        });

        if (qualityChecksList.length === 0) {
            return
        }

        window.addEventListener('record:save', loadQualityCheckData);
        window.addEventListener('record:check-recalculated', onCheckRecalculated)
        window.addEventListener('record:show-qc-details', onShowDetails)
        window.addEventListener('record:check-highlighted', onCheckHighlighted)

        activeItem = qualityChecksList[0].value;

        loadQualityCheckData()

        tick().then(() => {
            window.$(qualityCheckSelect).selectize({
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                onChange: function (value) {
                    activeItem = value
                    loadQualityCheckData()
                }
            });
        })

        return () => {
            window.removeEventListener('record:save', loadQualityCheckData)
            window.removeEventListener('record:check-recalculated', onCheckRecalculated)
            window.removeEventListener('record:show-qc-details', onShowDetails)
            window.removeEventListener('record:check-highlighted', onCheckHighlighted)
        }
    })

</script>

<div>
    <div style="margin-bottom: 10px">
        <select name="qualityChecks" bind:this={qualityCheckSelect}>
            {#each qualityChecksList as check}
                <option value="{check.value}">{check.text}</option>
            {/each}
        </select>
    </div>

    {#if data }
         <span style="{getValueStyle(data.value)}" on:click={recalculateCheck}
               class="colored-enum label" title="{Language.translate('recalculate','labels','QualityCheck')}"
               aria-expanded="false">{data.value === null ? '...' : (data.value === -1 ? Language.translate('N/A') : (data.value + '%'))}</span>
    {/if}

    {#if loading}
        <div style="text-align: center;margin-top: 10px">
            <img style="width: 40px; " class="preloader" src="client/img/atro-loader.svg" alt="loader">
        </div>
    {:else if data}
        <div style="margin-top: 10px;">
            <div style="margin-bottom: 10px; overflow: hidden; padding-left: 1px; padding-right: 1px;">
                <ContentFilter allFilters="{['passed','failed','skipped']}" scope="{scope}"
                               storageKey="qualityCheckRuleFilters"
                               translationScope="QualityCheckRule" translationField="status"
                               titleLabel="" onExecute="{onFilterChange}"
                               style="padding-bottom: 10px; display: inline-block"/>

                <div style="float: right; display: inline-block">
                    <button on:click={highlightCheck} style="margin-right: 10px;"
                            title="{Language.translate('highlight', 'labels', 'QualityCheck')}"><i
                            class="{'ph ph-highlighter '+ (highlightedCheckId===activeItem ? 'ph-fill highlight-active': '')}"></i>
                    </button>
                    <button class="refresh" on:click={()=>loadQualityCheckData(true)} style="float: right;"
                            title="{Language.translate('Refresh')}"><i
                            class="ph ph-arrows-clockwise"></i>
                    </button>
                </div>
            </div>
            {#each filteredRules as rule}
                <div style="margin-bottom: 10px">
                    <label class="control-label">
                        <span class="label-text">{rule.code}</span>
                    </label>
                    <div style="display: flex;justify-content: space-between;">
                        <p>{rule.name}</p>
                        <div class="rule-status" style="{getStatusStyle(rule.status)}"></div>
                    </div>
                    {#if rule.error}
                        <p class="rule-error">{rule.error}</p>
                    {/if}
                </div>
            {/each}
        </div>
    {/if}

</div>

<style>
    .rule-status {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-left: 10px;
    }

    .control-label {
        color: var(--label-color);
        font-size: 12px;
        font-weight: normal;
    }

    .rule-error {
        background: #ffeded;
        padding: 5px;
        border-radius: 3px;
        border: 1px solid #e9c8c8;
    }

    .highlight-active {
        color: #06c;
    }
</style>