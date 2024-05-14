<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{value}}">
{{#if idValue}}
{{#if value}}{{value}}{{else}}{{translate 'None'}}{{/if}}
{{else}}
    {{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
</a>