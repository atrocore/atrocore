{{#if isNotEmpty}}
<div> <span title="{{value}}{{unitSymbol}}">{{value}}{{unitSymbol}}</span></div>
{{else}}
{{#if isNull}}<div><span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span></div>{{/if}}
{{/if}}
