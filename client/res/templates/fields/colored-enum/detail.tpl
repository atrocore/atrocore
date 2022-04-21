{{#if isNotEmpty}}
<span class="label" style="display:inline-block;color:{{color}};background-color:{{backgroundColor}};font-size:{{fontSize}};font-weight:{{fontWeight}};border:{{border}}">
    {{translateOption value scope=scope field=name translatedOptions=translatedOptions}}
</span>
{{else}}
{{translate 'None'}}
{{/if}}
