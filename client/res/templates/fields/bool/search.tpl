
{{#if notNull }}
<input type="checkbox"{{#ifEqual searchParams.type 'isTrue'}} checked{{/ifEqual}} name="{{name}}" class="main-element">
{{else}}
<select name="{{name}}" class="form-control main-element">
    {{options params.options value scope=scope field=name translatedOptions=translatedOptions}}
</select>
{{/if}}