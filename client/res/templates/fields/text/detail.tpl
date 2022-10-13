{{#if isNotEmpty}}
{{#if params.useDisabledTextareaInViewMode}}
<textarea class="main-element form-control auto-height" name="{{name}}" {{#if params.maxLength}} maxlength="{{params.maxLength}}"{{/if}} disabled="disabled" rows="{{rows}}">{{value}}</textarea>
{{else}}
<span class="complex-text">{{complexText value}}</span>
{{/if}}
{{else}}

{{#if valueIsSet}}{{translate 'None'}}{{else}}...{{/if}}{{/if}}