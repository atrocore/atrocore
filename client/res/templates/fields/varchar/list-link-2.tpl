<a href="#{{scope}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{unitSymbol}}{{value}}">
    {{#if isNotEmpty}}
    {{unitSymbol}}{{value}}
    {{else}}
    {{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}
    {{/if}}
</a>
