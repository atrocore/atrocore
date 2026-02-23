{{#if selectedValues}}
    <div class="value-container">{{#each selectedValues}}<span class="label colored-multi-enum"{{#if description}} title="{{description}}"{{/if}}>{{#if backgroundColor}}<i style="background-color:{{backgroundColor}};"></i>{{/if}}<span>{{optionName}}</span></span>{{#unless @last}}<span class="separator">, </span>{{/unless}}{{/each}}</div>
{{else}}
{{#if value}}{{{value}}}{{else}}{{#if isNull}}<span data-action="loadData" class="text-gray" style="cursor: pointer" title="{{translate 'Load data'}}">...</span>{{else}}<span class="pre-label"> </span>{{/if}}{{/if}}
{{/if}}
