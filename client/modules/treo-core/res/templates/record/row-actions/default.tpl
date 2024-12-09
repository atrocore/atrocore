{{#if actionList.length}}
<div class="list-row-buttons btn-group pull-right">
    {{#if hasInheritedIcon}}
    <button type="button" class="btn btn-link btn-sm inherited">
        {{#if isInherited}}<span class="fas fa-link fa-sm" title="{{translate 'inherited'}}"></span>{{/if}}
    </button>
    {{/if}}
    <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
        <span class="fas fa-ellipsis-v"></span>
    </button>
    <ul class="dropdown-menu pull-right">
    {{#each actionList}}
        <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
    {{/each}}
    </ul>
</div>
{{/if}}