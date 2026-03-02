{{#if actorId}}
{{#if actorIsLink}}<a href="#User/view/{{actorId}}">{{actorName}}</a>{{else}}{{actorName}}{{/if}} <span class="text-muted">‹</span> {{#if delegatorIsLink}}<a href="#User/view/{{delegatorId}}">{{delegatorName}}</a>{{else}}{{delegatorName}}{{/if}}
{{else}}
{{#if idValue}}{{#if iconHtml}}{{{iconHtml}}}{{/if}}<a href="#User/view/{{idValue}}">{{nameValue}}</a>{{else}}{{#if valueIsSet}}{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}<span class="pre-label"> </span>{{/if}}{{else}}...{{/if}}{{/if}}
{{/if}}
