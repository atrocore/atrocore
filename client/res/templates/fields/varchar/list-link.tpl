<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{value}}">
    {{#if isNotEmpty}} {{{value}}} {{else}}{{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}{{/if}}
    {{#if unitFieldName}}{{#if unitValue}}{{unitValueTranslate}}{{else}}{{translate 'None'}}{{/if}}{{/if}}
</a>
