<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{value}}">
    {{#if isNotEmpty}} {{{value}}} {{else}}{{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}{{/if}}
    {{#if unitFieldName}}{{#if unitValue}}{{unitValueTranslate}}{{else}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{/if}}{{/if}}
</a>
