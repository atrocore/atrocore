{{#if notNull }}
<input type="checkbox"{{#if value}} checked{{/if}} name="{{name}}" class="main-element">
{{else}}
<select name="{{name}}" class="form-control main-element">
    {{options options value scope=scope field=name translatedOptions=translatedOptions}}
</select>
{{/if}}