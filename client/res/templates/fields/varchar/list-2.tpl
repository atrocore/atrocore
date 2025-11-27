{{#if isNotEmpty}}
<div><span title="{{unitSymbol}}{{value}}">{{unitSymbol}}{{value}}</span></div>
{{else}}
{{#if isNull}}<div><span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span></div>{{/if}}
{{/if}}
