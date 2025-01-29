<select name="{{name}}" class="form-control main-element">
    {{#if hasGroups }}
        {{groupedOptions groupOptions value scope=scope field=name translatedGroups=translatedGroups
                         prohibitedEmptyValue=prohibitedEmptyValue translatedOptions=translatedOptions}}
    {{ else }}
        {{options params.options value scope=scope field=name translatedOptions=translatedOptions}}
    {{/if}}
</select>

