<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{unitSymbol}}{{value}}">
    {{#if isNotEmpty}}
    {{unitSymbol}}{{value}}
    {{else}}
    {{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
    {{/if}}
</a>
