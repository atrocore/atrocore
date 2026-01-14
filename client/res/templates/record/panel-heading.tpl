<div class="pull-right btn-group">
    {{#if buttonList}}
        {{#each buttonList}}
        <button type="button" class="btn btn-{{#if ../style}}{{../style}}{{else}}default{{/if}} btn-sm action{{#if hidden}} hidden{{/if}}" data-action="{{action}}" data-panel="{{../name}}" {{#each data}} data-{{hyphen @key}}="{{./this}}"{{/each}} title="{{#if title}}{{translate title scope=../../scope}}{{/if}}">{{#if html}}{{{html}}}{{else}}{{translate label scope=../../../../scope}}{{/if}}</button>
        {{/each}}
    {{/if}}
    {{#if actionList}}
    <button type="button" class="btn btn-{{#if ../style}}{{../style}}{{else}}default{{/if}} btn-sm dropdown-toggle" data-toggle="dropdown">
        <i class="ph ph-list"></i>
    </button>
    <ul class="dropdown-menu">
        {{#each actionList}}
        {{#if this}}
        <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action{{#if hidden}} hidden{{/if}}" data-panel="{{../name}}" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{hyphen @key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../../../../../scope}}{{/if}}</a></li>
        {{else}}
        <li class="divider"></li>
        {{/if}}
        {{/each}}
    </ul>
    {{/if}}
    {{#if canClose }}
        <button type="button" class="btn btn-default btn-sm" data-action="closePanel" data-panel="{{name}}">
            <i class="ph ph-x"></i>
        </button>
    {{/if}}
</div>
<h4 class="panel-title">
    <span class="collapser" data-action="collapsePanel" data-panel="{{name}}">
        <i class="ph ph-caret-{{#if expanded}}down{{else}}right{{/if}}"></i>
    </span>
    {{#unless notRefreshable}}
    <span class="panel-title-text" style="cursor: pointer;" class="action" title="{{translate 'clickToRefresh' category='messages'}}" data-action="refresh" data-panel="{{name}}">
        {{/unless}}
        {{#if titleHtml}}
            {{{titleHtml}}}
        {{else}}
            {{title}}
        {{/if}}
        {{#unless notRefreshable}}
        </span>
    {{/unless}}
</h4>