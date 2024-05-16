{{#if value}}
	<a href="{{url}}" target="_blank" title="{{value}}">{{value}}</a>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
