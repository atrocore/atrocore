{{#if selectedValues}}
    {{#each selectedValues}}<span class="label colored-multi-enum"{{#if description}} title="{{description}}"{{/if}}>{{#if backgroundColor}}<i style="background-color:{{backgroundColor}};"></i>{{/if}}<span>{{optionName}}</span></span>{{#unless @last}}<span class="separator">, </span>{{/unless}}{{/each}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}