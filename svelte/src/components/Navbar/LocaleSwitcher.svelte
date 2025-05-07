<script lang="ts">
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {UserData} from "../../utils/UserData";
    import {LayoutManager} from "../../utils/LayoutManager";
    import {Language} from "../../utils/Language";

    let locales = Config.get('locales') || {}
    let languages = (Config.get('inputLanguageList') || []).reduce((res, item) => {
        res[item] = Config.get('referenceData').Language?.[item]
        return res
    }, {})

    let locale = UserData.get()?.user?.localeId
    if (!locale || !locales[locale]) {
        locale = Config.get('locale')
    }

    let mainLanguageCode = ''

    for (const [code, language] of Object.entries(Config.get('referenceData').Language || {})) {
        if (language.role === 'main') {
            mainLanguageCode = code
        }
    }

    let disabledLanguages = UserData.get()?.user?.disabledLanguages || []
    let languagesLabel

    if (locale && locale !== mainLanguageCode) {
        // remove language for selected locale if exists
        if (locale && locales[locale]?.code && languages[locales[locale].code]) {
            delete languages[locales[locale].code]
            // add main locale language
            languages[mainLanguageCode] = Config.get('referenceData').Language[mainLanguageCode]
        }
    }

    disabledLanguages = disabledLanguages.filter(code => !!languages[code])

    function setAllLanguages(event) {
        enabledLanguages = Object.keys(languages)
        onLanguageChange()
    }

    let enabledLanguages = Object.keys(languages).filter(item => !disabledLanguages.includes(item))

    $: {
        if (enabledLanguages.length === 0) {
            languagesLabel = Language.translate('None', 'labels', 'Global')
        } else if (enabledLanguages.length === Object.keys(languages).length) {
            languagesLabel = Language.translate('allLanguages', 'labels', 'Global')
        } else {
            languagesLabel = enabledLanguages.map(code => languages[code].name).join(', ')
        }
    }

    async function onLocaleChange() {
        const userData = UserData.get()
        await Utils.patchRequest('/UserProfile/' + userData.user.id, {localeId: locale})
        window.location.reload()
    }

    async function onLanguageChange() {
        const userData = UserData.get()
        const disabledLanguages = Object.keys(languages).filter(item => !enabledLanguages.includes(item));
        await Utils.patchRequest('/UserProfile/' + userData.user.id, {
            disabledLanguages: disabledLanguages
        })

        LayoutManager.clearListAndDetailCache()
        // emit event to reload layouts
        window.Backbone.trigger('change:disabled-languages', disabledLanguages)
    }
</script>

<div class="btn-group" style="display:flex; align-items: center; padding: 0 10px; height: 100%;">
    <select class="form-control locale-switcher" style="max-width: 100px; flex: 1;" bind:value={locale}
            on:change={onLocaleChange}>
        {#each Object.entries(locales) as [id, locale] }
            <option value="{id}">
                {locale.name}
            </option>
        {/each}
    </select>
    {#if Object.keys(languages).length > 0}
        <div class="dropdown">
            <select data-toggle="dropdown" class="form-control language-switcher"
                    on:mousedown={event => event.preventDefault()}
                    style="max-width: 300px; flex: 1;">
                <option value="" selected>{languagesLabel}</option>
            </select>
            <div class="dropdown-menu" style="padding: 10px; min-width: 180px">
                <h5 style="margin-top: 0">{Language.translate('additionalLanguages', 'labels', 'Global')}</h5>
                <ul style="padding: 0" on:click={event => event.stopPropagation()}>
                    {#each Object.entries(languages).sort((v1, v2) => v1[1].name.localeCompare(v2[1].name)) as [code, language] }
                        <li class="checkbox">
                            <label>
                                <input type="checkbox" bind:group={enabledLanguages} value="{code}"
                                       on:change={onLanguageChange}>
                                {language.name}
                            </label>
                        </li>
                    {/each}
                </ul>
                {#if enabledLanguages.length < Object.keys(languages).length }
                    <a href="javascript:" on:click={setAllLanguages}
                       style="margin-top: 10px">{Language.translate('selectAll', 'labels', 'Global')}</a>
                {/if}
            </div>
        </div>

    {/if}
</div>

<style>
    .locale-switcher {
        border-bottom-left-radius: 5px;
        border-top-left-radius: 5px;
    }

    .language-switcher {
        border-left: none;
        border-bottom-right-radius: 5px;
        border-top-right-radius: 5px;
    }

    .language-switcher, .locale-switcher {
        color: var(--nav-font-color);
        background-color: var(--nav-menu-background); /* rgba(0, 0, 0, 0.05); */
        border-color: rgba(var(--nav-font-color-rgb), 0.2);
    }
</style>