<select name="{{name}}" class="form-control main-element" style="color:{{color}};background-color:{{backgroundColor}};font-weight:{{fontWeight}};border:{{border}}">
    {{#if hasGroups }}
        {{groupedOptions groupOptions value scope=scope field=name translatedGroups=translatedGroups
                         prohibitedEmptyValue=prohibitedEmptyValue translatedOptions=translatedOptions}}
    {{ else }}
        {{#each options}}
            <option style="color:{{color}};background-color:{{backgroundColor}};font-weight:{{fontWeight}}" value="{{value}}" {{#if selected}}selected{{/if}}>
                {{translateOption value scope=../scope field=../name translatedOptions=../translatedOptions}}
            </option>
        {{/each}}
    {{/if}}
</select>
