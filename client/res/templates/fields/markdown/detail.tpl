{{#if isNotEmpty}}
    <span class="complex-text">{{complexText value}}</span>
{{else}}
    {{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}
        <span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}
{{/if}}