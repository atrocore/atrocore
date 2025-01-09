{{#if isNotEmpty}}
    <span title="{{unitSymbol}}{{value}}">{{unitSymbol}}{{value}}</span>
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
