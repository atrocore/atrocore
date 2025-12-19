{{#if label}}<a href="{{url}}">{{label}}</a>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}