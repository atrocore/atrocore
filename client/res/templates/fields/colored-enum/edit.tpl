<select name="{{name}}" class="form-control main-element" style="color:{{color}};background-color:{{backgroundColor}};font-weight:{{fontWeight}};border:{{border}}">
    {{#each options}}
    <option style="color:{{color}};background-color:{{backgroundColor}};font-weight: {{fontWeight}};" value="{{value}}" {{#if selected}}selected{{/if}}>
        {{translateOption value scope=../scope field=../name translatedOptions=../translatedOptions}}
    </option>
    {{/each}}
</select>
