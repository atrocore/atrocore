<select name="{{name}}" class="form-control main-element">
    {{#if hasGroups }}
        {{options params.options value scope=scope field=name translatedOptions=translatedOptions}}
    {{ else }}
        {{groupedOptions groups value scope=scope field=name translatedGroups=translatedGroups
                         prohibitedEmptyValue=prohibitedEmptyValue translatedOptions=translatedOptions}}
    {{/if}}
</select>

