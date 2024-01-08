{{#if isNotEmpty}}
    {{unitSymbol}}{{value}}
{{else}}
    {{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
{{/if}}
