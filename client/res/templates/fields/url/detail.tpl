{{#if value}}<a href="{{url}}" target="_blank">{{value}}</a>
{{else}}
{{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}
{{/if}}