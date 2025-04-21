<script lang="ts">
    import {Config} from "../../utils/Config";
    import {Utils} from "../../utils/Utils";
    import {UserData} from "../../utils/UserData";

    let locales = Config.get('locales')
    let languages = (Config.get('inputLanguageList') || []).reduce((res, item) => {
        res[item] = Config.get('referenceData').Language?.[item]
        return res
    }, {})

    let locale = UserData.get()?.user?.localeId || Config.get('locale')
    let inputLanguages = []
    let languagesLabel

    if (locale !== Config.get('locale')) {
        // remove language for selected locale if exists
        if (languages[locales[locale].code]) {
            delete languages[locales[locale].code]
            // add main locale language
            const mainLanguageCode = locales['main'].code
            const mainLanguage = Config.get('referenceData').Language[mainLanguageCode]
            if (mainLanguage) {
                languages[mainLanguageCode] = mainLanguage
            }
        }
    }

    function unsetLanguages(event) {
        inputLanguages = []
    }

    $: {
        if (inputLanguages.length === 0) {
            languagesLabel = 'No additional language'
        } else {
            languagesLabel = inputLanguages.map(code => languages[code].name).join(', ')
        }
    }

    async function onLocaleChange() {
        const userData = UserData.get()
        await Utils.patchRequest('/UserProfile/' + userData.user.id, {localeId: locale})
        window.location.reload()
    }

    function onLanguageChange() {
        console.log(inputLanguages)
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
                    style="max-width: 100px; flex: 1;">
                <option value="" selected>{languagesLabel}</option>
            </select>
            <div class="dropdown-menu" style="padding: 10px; min-width: 180px">
                <h5>Additional Languages</h5>
                <ul style="padding: 0" on:mousedown={event => event.stopPropagation()}>
                    {#each Object.entries(languages).sort((v1, v2) => v1[1].name.localeCompare(v2[1].name)) as [code, language] }
                        <li class="checkbox">
                            <label>
                                <input type="checkbox" bind:group={inputLanguages} value="{code}"
                                       on:change={onLanguageChange}>
                                {language.name}
                            </label>
                        </li>
                    {/each}
                </ul>
                {#if inputLanguages.length }
                    <a href="javascript:" on:click={unsetLanguages} style="margin-top: 10px">Unset All</a>
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
        border-bottom-right-radius: 5px;
        border-top-right-radius: 5px;
    }
</style>