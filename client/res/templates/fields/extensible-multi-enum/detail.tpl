{{#if selectedValues}}
{{#each selectedValues}}<span class="label colored-multi-enum" title="{{description}}" style="color:{{color}};background-color:{{backgroundColor}};font-size:{{fontSize}};font-weight:{{fontWeight}};border:{{border}}">{{optionName}}</span>{{/each}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}