<a href="#{{scope}}/view/{{entityId}}" class="link" data-entity-id="{{entityId}}" data-id="{{id}}" title="{{value}}">
    {{#if isNotEmpty}} {{{value}}} {{else}}{{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}{{/if}}
</a>
