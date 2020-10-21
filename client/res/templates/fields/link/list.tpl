{{#if nameValue}}
  {{#if idValue}}
    <a href="#{{foreignScope}}/view/{{idValue}}" title="{{nameValue}}">{{nameValue}}</a>
  {{else}}
    {{nameValue}}
  {{/if}}
{{/if}}

