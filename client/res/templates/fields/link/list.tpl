{{#if nameValue}}
  {{#if idValue}}
    <a href="#{{foreignScope}}/view/{{idValue}}" title="{{nameValue}}">{{nameValue}}</a>
  {{else}}
    {{nameValue}}
  {{/if}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}

