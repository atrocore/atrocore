<button type="button" class="btn btn-default collapse-panel" data-action="collapsePanel">
    <i class="ph ph-caret-left toggle-icon-left hidden"></i>
    <i class="ph ph-caret-right toggle-icon-right"></i>
</button>
{{#each panelList}}
<div class="panel panel-{{#if style}}{{style}}{{else}}default{{/if}} panel-{{name}}{{#if hidden}} hidden{{/if}}{{#if sticked}} sticked{{/if}}" data-name="{{name}}" data-name="{{name}}">
    {{#if label}}
    <div class="panel-heading">
        <div class="pull-right btn-group">
            {{#if buttonList}}
                {{#each buttonList}}
                <button type="button" class="btn btn-{{#if ../style}}{{../style}}{{else}}default{{/if}} btn-sm action{{#if hidden}} hidden{{/if}}" data-action="{{action}}" data-panel="{{../name}}" {{#each data}} data-{{hyphen @key}}="{{this}}"{{/each}} title="{{#if title}}{{translate title scope=../scope}}{{/if}}">{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</button>
                {{/each}}
            {{/if}}
            {{#if actionList}}
                <button type="button" class="btn btn-{{#if style}}{{style}}{{else}}default{{/if}} btn-sm dropdown-toggle" data-toggle="dropdown">
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu">
                    {{#each actionList}}
                    {{#if this}}
                    <li><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action{{#if hidden}} hidden{{/if}}" {{#if action}} data-panel="{{../name}}" data-action="{{action}}"{{/if}}{{#each data}} data-{{hyphen @key}}="{{this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
                    {{else}}
                    <li class="divider"></li>
                    {{/if}}
                    {{/each}}
                </ul>
            {{/if}}
        </div>
        <h4 class="panel-title">
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
        </h4>
    </div>
    {{/if}}
    <div class="panel-body{{#if isForm}} panel-body-form{{/if}}" data-name="{{name}}">
        {{{var name ../this}}}
    </div>
</div>
{{/each}}
<div class="side-panel-resizer"></div>
