<div class="value-container">
    <span data-name="datetimeField">{{{datetimeField}}}</span>
    <span class="extra">
        <span class="text-muted" style="padding: 0 2px">⋅</span>
        <span data-name="userField">
            {{#if delegatorId }}{{#if actorIsLink }}<a href="#User/view/{{{actorId}}}">{{{actorName}}}</a>{{else}}{{{actorName}}}{{/if}} <span class="text-muted">‹</span> {{#if delegatorIsLink }}<a href="#User/view/{{{delegatorId}}}">{{{delegatorName}}}</a>{{else}}{{{delegatorName}}}{{/if}}
            {{else}}
            {{#if actorIsLink }}<a href="#User/view/{{{actorId}}}">{{{actorName}}}</a>{{else}}{{{actorName}}}{{/if}}
            {{/if}}
        </span>
    </span>
</div>