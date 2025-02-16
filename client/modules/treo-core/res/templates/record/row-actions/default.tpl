{{#if actionList.length}}
<div class="list-row-buttons btn-group pull-right">
    <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
        <span class="fas fa-ellipsis-v"></span>
    </button>
    <ul class="dropdown-menu pull-right">
    {{#each actionList}}
        {{#if divider}}
            <li class="divider"></li>
        {{ else if preloader }}
            <li class="preloader"><a href="javascript:"><img class="preloader" style="height:12px;margin-top: 5px" src="client/img/atro-loader.svg"></a> </li>
        {{else}}
            <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
        {{/if}}
    {{/each}}
    </ul>
</div>
{{/if}}