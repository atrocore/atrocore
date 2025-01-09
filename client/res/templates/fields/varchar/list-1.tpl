{{#if isNotEmpty}}
    <span title="{{value}}{{unitSymbol}}">{{value}}{{unitSymbol}}</span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
