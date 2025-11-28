<a href="#{{scope}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{value}}{{unitSymbol}}">
    {{#if isNotEmpty}}
    {{value}}{{unitSymbol}}
    {{else}}
    {{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}
    {{/if}}
</a>
