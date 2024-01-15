<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{value}}{{unitSymbol}}">
    {{#if isNotEmpty}}
    {{value}}{{unitSymbol}}
    {{else}}
    {{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
    {{/if}}
</a>
