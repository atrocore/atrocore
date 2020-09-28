<span class="{{#if mutedText}}text-muted{{/if}}">
{{#if isNotEmpty}}{{{value}}}{{else}}
{{#if valueIsSet}}{{{translate 'None'}}}{{else}}...{{/if}}
{{/if}}
</span>