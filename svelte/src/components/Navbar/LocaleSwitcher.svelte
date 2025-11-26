<script lang="ts">
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {UserData} from "../../utils/UserData";
    import {LayoutManager} from "../../utils/LayoutManager";
    import {Language} from "../../utils/Language";
    import {Storage} from "../../utils/Storage";

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

    let locale = Storage.get('user', 'locale')
    if (!locale || !locales[locale]) {
        locale = UserData.get()?.user?.localeId
        if (!locale || !locales[locale]) {
            locale = Config.get('locale')
            if (!locales[locale]) {
                locale = 'main'
            }
        }
    }

    let disabledLanguages = UserData.get()?.user?.disabledLanguages || []
    let defaultLanguageCode = mainLanguageCode

    if (locale && locales[locale]?.language && languages[locales[locale].language]) {
        defaultLanguageCode = locales[locale].language
    }

    disabledLanguages = disabledLanguages.filter(code => !!languages[code])

    function setAllLanguages(event) {
        enabledLanguages = Object.keys(languages).filter(item => item !== defaultLanguageCode)
        onLanguageChange()
    }

    let enabledLanguages = Object.keys(languages).filter(item => !disabledLanguages.includes(item))


    async function onLocaleChange() {
        const userData = UserData.get()
        const code = locales[locale]?.language
        const newDefaultCode = code && languages[code] ? code : mainLanguageCode

        checkConfirmLeaveOut(async () => {
            if (locale === UserData.get().user.localeId) {
                Storage.clear('user', 'locale')
            } else {
                Storage.set('user', 'locale', locale)
            }

            LayoutManager.clearListAndDetailCache()

            await Utils.patchRequest('/UserProfile/' + userData.user.id, {
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

<div class="button-group input-group" style="display:flex; align-items: center; padding: 0 10px; height: 100%;">
    {#if Object.keys(locales).length > 1}
        <div>
            <select class="form-control locale-switcher"
                    title="{Language.translate('Locale','scopeNamesPlural','Global')}"
                    style="max-width: 100px;" name="locales"
                    bind:value={locale}
                    on:change={onLocaleChange}>
                <optgroup label="{Language.translate('Locale','scopeNamesPlural','Global')}">
                </optgroup>
                {#each Object.entries(locales) as [id, locale] }
                    <option value="{id}">
                        {locale.name}
                    </option>
                {/each}
            </select>
        </div>
    {/if}
    {#if Object.keys(languages).length > 1}
        <div class="dropdown has-content">
            <button data-toggle="dropdown" class="filter-switcher" aria-expanded="false" style="padding: 6px 12px">
                <i class="{`ph ph-${enabledLanguages.length >= Object.keys(languages).length - 1 ? 'globe-simple':'translate' }`}"
                   style="display: block"></i>
            </button>
            <div class="dropdown-menu" style="padding: 10px; min-width: 180px">
                <h5 style="margin-top: 0">{Language.translate('contentLanguages', 'labels', 'Global')}</h5>
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
    button, .locale-switcher {
        color: var(--toolbar-font-color);
        background-color: var( --toolbar-background-color);
        border-color: rgba(var(--nav-font-color-rgb, 0, 0, 0), 0.2);
        cursor: pointer;
    }

    select, option, optgroup {
        color: var(--toolbar-font-color);
        background-color: var(--toolbar-background-color);
    }

    button:hover, .locale-switcher:hover, button:focus, .locale-switcher:focus {
        background-color: rgba(0, 0, 0, 0.03);
    }

    button:active, .locale-switcher:active {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .button-group > :first-child > button, .button-group > :first-child > .locale-switcher {
        border-bottom-left-radius: 5px;
        border-top-left-radius: 5px;
    }

    .button-group > :last-child > button, .button-group > :last-child > .locale-switcher {
        border-bottom-right-radius: 5px;
        border-top-right-radius: 5px;
    }

    .button-group > :nth-child(2) > button {
        border-left-width: 0;
    }
</style>