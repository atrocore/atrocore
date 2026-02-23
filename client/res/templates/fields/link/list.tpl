{{#if color}}
<span class="label colored-enum"{{#if description}} title="{{description}}"{{/if}}>{{#if backgroundColor}}<i style="background-color:{{backgroundColor}};"></i>{{/if}}<span>{{nameValue}}</span></span>
{{else}}
{{#if nameValue}}
{{#if idValue}}
<a href="#{{foreignScope}}/view/{{idValue}}" title="{{nameValue}}">{{nameValue}}</a>
{{else}}
{{nameValue}}
{{/if}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
{{/if}}