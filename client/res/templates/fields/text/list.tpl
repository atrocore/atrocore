{{#if isNotEmpty}}
    {{#if params.useDisabledTextareaInViewMode}}
        <textarea class="main-element form-control auto-height" name="{{name}}" disabled="disabled" rows="{{rows}}">{{value}}</textarea>
    {{else}}
        <span class="complex-text">{{complexText value}}</span>
    {{/if}}
{{/if}}
