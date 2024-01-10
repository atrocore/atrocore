{{#if isNotEmpty}}
    {{value}}{{unitSymbol}}
{{else}}
    {{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
{{/if}}
