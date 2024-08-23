{{#if isNotEmpty}}
    {{#if params.useDisabledTextareaInViewMode}}
        <textarea class="main-element form-control auto-height" name="{{name}}" disabled="disabled" rows="{{rows}}">{{value}}</textarea>
    {{else}}
        <span>{{breaklines value}}</span>
    {{/if}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
