<div class="pull-right btn-group">
    {{#if buttonList}}
        {{#each buttonList}}
        <button type="button" class="btn btn-{{#if ../../style}}{{../../style}}{{else}}default{{/if}} btn-sm action{{#if hidden}} hidden{{/if}}" data-action="{{action}}" data-panel="{{../../name}}" {{#each data}} data-{{hyphen @key}}="{{./this}}"{{/each}} title="{{#if title}}{{translate title scope=../../scope}}{{/if}}">{{#if html}}{{{html}}}{{else}}{{translate label scope=../../../../scope}}{{/if}}</button>
        {{/each}}
    {{/if}}
    {{#if actionList}}
    <button type="button" class="btn btn-{{#if ../style}}{{../style}}{{else}}default{{/if}} btn-sm dropdown-toggle" data-toggle="dropdown">
        <span class="caret"></span>
    </button>
    <ul class="dropdown-menu">
        {{#each actionList}}
        {{#if this}}
        <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action{{#if hidden}} hidden{{/if}}" data-panel="{{../../../name}}" {{#if action}} data-action={{action}}{{/if}}{{#each data}} data-{{hyphen @key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../../../../../scope}}{{/if}}</a></li>
        {{else}}
        <li class="divider"></li>
        {{/if}}
        {{/each}}
    </ul>
    {{/if}}
    {{#if canClose }}
        <button type="button" class="btn btn-default btn-sm" data-action="closePanel" data-panel="{{name}}">
            <span class="fas fa-times"></span>
        </button>
    {{/if}}
</div>
<h4 class="panel-title">
    <span class="collapser" data-action="collapsePanel" data-panel="{{name}}">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"><path d="M480-80 240-320l44-44 196 196 196-196 44 44L480-80ZM284-596l-44-44 240-240 240 240-44 44-196-196-196 196Z"></path></svg>
    </span>
    {{#unless notRefreshable}}
    <span style="cursor: pointer;" class="action" title="{{translate 'clickToRefresh' category='messages'}}" data-action="refresh" data-panel="{{name}}">
        {{/unless}}
        {{#if titleHtml}}
            {{{titleHtml}}}
        {{else}}
            {{title}}
        {{/if}}
        {{#unless notRefreshable}}
        </span>
    {{/unless}}
    <span class="collapser fas {{#if expanded}}fa-chevron-up{{else}}fa-chevron-down{{/if}}" data-action="collapsePanel" data-panel="{{name}}"></span>
</h4>