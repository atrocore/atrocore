{{#if idValue}}
<span class="label colored-enum"{{#if description}} title="{{description}}"{{/if}}>{{#if backgroundColor}}<i style="background-color:{{backgroundColor}};"></i>{{/if}}<span>{{nameValue}}</span></span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}