{{#if isNotEmpty}}{{#if useDisabledTextareaInViewMode}}<textarea class="main-element form-control auto-height" name="{{name}}" {{#if params.maxLength}} maxlength="{{params.maxLength}}"{{/if}} disabled="disabled" rows="{{rows}}">{{value}}</textarea>{{else}}<span class="complex-text">{{complexText value}}</span>{{/if}}
{{else}}
{{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}
{{/if}}