<select name="{{name}}" class="form-control main-element">
    <option value=""></option>
    {{#if hasGroups }}
        {{groupedOptions groupOptions value scope=scope field=name translatedGroups=translatedGroups
                         required=required translatedOptions=translatedOptions}}
    {{ else }}
        {{options params.options value scope=scope field=name translatedOptions=translatedOptions}}
    {{/if}}
</select>

