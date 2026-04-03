{{#if actorId}}
{{#if actorIsLink}}<a href="#User/view/{{actorId}}" title="{{actorName}}">{{actorName}}</a>{{else}}{{actorName}}{{/if}} <span class="text-muted">‹</span> {{#if delegatorIsLink}}<a href="#User/view/{{delegatorId}}" title="{{delegatorName}}">{{delegatorName}}</a>{{else}}{{delegatorName}}{{/if}}
{{else}}
{{#if nameValue}}
{{#if idValue}}
<a href="#User/view/{{idValue}}" title="{{nameValue}}">{{nameValue}}</a>
{{else}}
{{nameValue}}
{{/if}}
{{else}}
{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}
{{/if}}
{{/if}}
