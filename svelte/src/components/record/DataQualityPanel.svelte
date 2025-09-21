<script lang="ts">
    import {onMount, tick} from "svelte";
    import {Metadata} from "../../utils/Metadata";
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {Language} from "../../utils/Language";
    import ContentFilter from "./ContentFilter.svelte";

    export let scope;
    export let id;

    let qualityCheckSelect;
    let selectizeObject;
    let qualityChecksList = [];

    let activeItem = null
    let loading = false
    let data = null
    let selectedFilters = []
    let filteredRules = []

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
        const borderColor = status === 'passed' ? '#afc89a' : (status === 'failed' ? '#ab655f' : '#888888');
        return `background-color: ${backgroundColor};border: 2px solid ${borderColor};`
    }

    async function loadQualityCheckData() {
        loading = true
        data = null

        const resp = await Utils.getRequest('/QualityCheck/action/getEntityData', {
            entityName: scope,
            entityId: id,
            qualityCheckId: activeItem
        })

        data = await resp.json()
        loading = false
    }

    window.addEventListener('record:save', loadQualityCheckData);

    onMount(() => {

        Object.entries(Metadata.get(['entityDefs', scope, 'fields'])).forEach(([field, defs]) => {
            if (defs.dataQualityCheck) {
                let text;
                Object.entries(Config.get('referenceData').QualityCheck).forEach(([key, check]) => {
                    if (check.id === defs.qualityCheckId) {
                        text = check.name;
                    }
                });

                qualityChecksList.push({
                    value: defs.qualityCheckId,
                    text: text
                });
            }
        });

        if (qualityChecksList.length === 0) {
            return
        }

        activeItem = qualityChecksList[0].value;


        loadQualityCheckData()

        tick().then(() => {
            selectizeObject = window.$(qualityCheckSelect).selectize({
                valueField: 'value',
                labelField: 'text',
                searchField: ['text'],
                onChange: function (value) {
                    activeItem = value
                    loadQualityCheckData()
                }
            });
        })
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

    {#if loading}
        <img style="width: 40px; margin: 0 auto" class="preloader" src="client/img/atro-loader.svg" alt="loader">
    {/if}

    {#if data}
        <span style="{getValueStyle(data.value)}" on:click={loadQualityCheckData}
              class="colored-enum label"
              aria-expanded="false">{data.value >= 0 ? (data.value + '%') : Language.translate('N/A')}</span>

        <div style="border-top: 2px solid #ddd;margin-top: 10px;padding-top: 10px">
            <div style="display: flex;justify-content: space-between;margin-bottom: 15px">
                <ContentFilter allFilters="{['passed','failed','skipped']}" storageKey="qualityCheckRuleFilters"
                               translationScope="QualityCheckRule" translationField="status"
                               titleLabel="" onExecute="{onFilterChange}" style="padding-bottom: 0"/>

                <button class="refresh" on:click={loadQualityCheckData} title="{Language.translate('Refresh')}"><i
                        class="ph ph-arrows-clockwise"></i>
                </button>
            </div>
            {#each filteredRules as rule}
                <div style="display: flex;justify-content: space-between; margin-bottom: 10px">
                    <div style="word-break: break-all">
                        <label class="control-label">
                            <span class="label-text">{rule.code}</span>
                        </label>
                        <p>{rule.name}</p>
                        {#if rule.error}
                            <p>{rule.error}</p>
                        {/if}
                    </div>
                    <div class="rule-status" style="{getStatusStyle(rule.status)}"></div>
                </div>
            {/each}
        </div>
    {/if}
</div>

<style>
    .rule-status {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border-width: 2px;
        border-style: solid;
        flex-shrink: 0;
    }

    .control-label {
        color: var(--label-color);
        font-weight: 500;
    }
</style>