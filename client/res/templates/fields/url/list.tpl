{{#if label}}<a href="{{url}}" class="url-field-link">{{label}}</a>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}