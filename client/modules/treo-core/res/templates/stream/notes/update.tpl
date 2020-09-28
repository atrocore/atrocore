{{#unless noEdit}}
<div class="pull-right right-container">
{{{right}}}
</div>
{{/unless}}

<div class="stream-head-container">
    <div class="pull-left">
        {{{avatar}}}
    </div>
    <div class="stream-head-text-container">
        <span class="text-muted message">{{{message}}}</span> <a href="javascript:" data-action="expandDetails"><span class="fas fa-chevron-down"></span></a>
    </div>
</div>

<div class="hidden details stream-details-container">
    <span>
        {{#each fieldsArr}}
        <span class="text-bold">{{#if customLabel}}{{{customLabel}}}{{else}}{{translate field category='fields' scope=../../parentType}}{{/if}}:</span> <div class="was">{{{var was ../this}}}</div> &raquo; <div class="became">{{{var became ../this}}}</div>
            <br>
        {{/each}}
    </span>
</div>

<div class="stream-date-container">
    <span class="text-muted small">{{{createdAt}}}</span>
</div>
