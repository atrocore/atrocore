<script lang="ts">
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {UserData} from "../../utils/UserData";
    import {LayoutManager} from "../../utils/LayoutManager";
    import {Language} from "../../utils/Language";

    export let checkConfirmLeaveOut: Function;
    let mainLanguageCode = ''

    for (const [code, language] of Object.entries(Config.get('referenceData').Language || {})) {
        if (language.role === 'main') {
            mainLanguageCode = code
        }
    }

    let locales = Config.get('locales') || {}
    let languages = [mainLanguageCode, ...(Config.get('inputLanguageList') || [])].reduce((res, item) => {
        res[item] = Config.get('referenceData').Language?.[item]
        return res
    }, {})

    let locale = UserData.get()?.user?.localeId
    if (!locale || !locales[locale]) {
        locale = Config.get('localeId')
        if (!locales[locale]) {
            locale = 'main'
        }
    }
    console.log(locale, locales)

    let disabledLanguages = UserData.get()?.user?.disabledLanguages || []
    let defaultLanguageCode = mainLanguageCode

    if (locale && locales[locale]?.code && languages[locales[locale].code]) {
        defaultLanguageCode = locales[locale].code
    }

    disabledLanguages = disabledLanguages.filter(code => !!languages[code])

    function setAllLanguages(event) {
        enabledLanguages = Object.keys(languages).filter(item => item !== defaultLanguageCode)
        onLanguageChange()
    }

    let enabledLanguages = Object.keys(languages).filter(item => !disabledLanguages.includes(item))


    async function onLocaleChange() {
        const userData = UserData.get()
        const code = locales[locale]?.code
        const newDefaultCode = code && languages[code] ? code : mainLanguageCode

        checkConfirmLeaveOut(async () => {
            await Utils.patchRequest('/UserProfile/' + userData.user.id, {
                localeId: locale,
                disabledLanguages: Object.keys(languages).filter(item => item !== newDefaultCode)
            })
            window.location.reload()
        })
    }

    async function onLanguageChange() {
        const userData = UserData.get()
        const disabledLanguages = Object.keys(languages).filter(item => item !== defaultLanguageCode && !enabledLanguages.includes(item));
        await Utils.patchRequest('/UserProfile/' + userData.user.id, {
            disabledLanguages: disabledLanguages
        })

        LayoutManager.clearListAndDetailCache()
        // emit event to reload layouts
        for (const [_, view] of (window.languageObservableViews?.entries() ?? [])) {
            view?.trigger('change:disabled-languages', disabledLanguages)
        }
    }
</script>

<div class="btn-group input-group" style="display:flex; align-items: center; padding: 0 10px; height: 100%;">
    {#if Object.keys(locales).length > 1}
        <select class="form-control locale-switcher"
                style="max-width: 100px; flex: 1;padding: 7px 14px;line-height: 17px"
                bind:value={locale}
                on:change={onLocaleChange}>
            {#each Object.entries(locales) as [id, locale] }
                <option value="{id}">
                    {locale.name}
                </option>
            {/each}
        </select>
    {/if}
    {#if Object.keys(languages).length > 1}
        <div class="dropdown has-content">
            <button data-toggle="dropdown" class="btn btn-default filter-switcher" aria-expanded="false">
                <i class="{`ph ph-${enabledLanguages.length === Object.keys(languages).length - 1 ? 'globe':'globe-simple' }`}"></i>
            </button>
            <div class="dropdown-menu" style="padding: 10px; min-width: 180px">
                <h5 style="margin-top: 0">{Language.translate('additionalLanguages', 'labels', 'Global')}</h5>
                <ul style="padding: 0" on:click={event => event.stopPropagation()}>
                    {#if defaultLanguageCode}
                        <li class="checkbox">
                            <label style="cursor: not-allowed">
                                <input type="checkbox" checked disabled>
                                {languages[defaultLanguageCode]?.name}
                            </label>
                        </li>
                    {/if}
                    {#each Object.entries(languages).filter(v => v[0] !== defaultLanguageCode).sort((v1, v2) => v1[1].name.localeCompare(v2[1].name)) as [code, language] }
                        <li class="checkbox">
                            <label>
                                <input type="checkbox" bind:group={enabledLanguages} value="{code}"
                                       on:change={onLanguageChange}>
                                {language.name}
                            </label>
                        </li>
                    {/each}
                </ul>
                {#if enabledLanguages.length < Object.keys(languages).length - 1 }
                    <a href="javascript:" on:click={setAllLanguages}
                       style="margin-top: 10px">{Language.translate('selectAll', 'labels', 'Global')}</a>
                {/if}
            </div>
        </div>
    {/if}
</div>

<style>
    .btn, .locale-switcher {
        /*background-color: transparent;*/
        color: var(--nav-font-color);
        background-color: var(--nav-menu-background); /* rgba(0, 0, 0, 0.05); */
        border-color: rgba(var(--nav-font-color-rgb, 0, 0, 0), 0.2);
    }

    .btn-group > :first-child > .btn, .btn-group > .locale-switcher:first-child {
        border-bottom-left-radius: 5px;
        border-top-left-radius: 5px;
    }

    .btn-group > :last-child > .btn, .btn-group > .locale-switcher:last-child {
        border-bottom-right-radius: 5px;
        border-top-right-radius: 5px;
    }

    .btn-group > :nth-child(2) > .btn {
        border-left-width: 0;
    }
</style>