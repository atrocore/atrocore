<div class="row">
    <div>
        {{{header}}}
    </div>
    <div class="header-buttons-container">
        <div class="header-buttons btn-group pull-right">
            <div class="header-items">
                {{#each items.buttons}}
                <a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} {{#if title}} title="{{title}}" {{/if}} style="{{cssStyle}}" class="btn btn-{{#if style}}{{style}}{{else}}default{{/if}} action{{#if hidden}} hidden{{/if}}" data-name="{{name}}" data-action="{{action}}"{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>
                    {{#if iconHtml}}{{{iconHtml}}}{{/if}}
                    {{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}
                </a>
                {{/each}}

                {{#if items.actions}}
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                        {{translate 'Actions'}} <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu pull-right">
                        {{#each items.actions}}
                        <li class="{{#if hidden}}hidden{{/if}}"><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" style="{{cssStyle}}" data-name="{{name}}" data-action="{{action}}"{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
                        {{/each}}
                    </ul>
                </div>
                {{/if}}

                {{#if items.dropdown}}
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu pull-right">
                        {{#each items.dropdown}}
                        <li class="{{#if hidden}}hidden{{/if}}"><a {{#if link}}href="{{link}}"{{else}}href="javascript:"{{/if}} class="action" data-name="{{name}}" data-action="{{action}}"{{#each data}} data-{{@key}}="{{./this}}"{{/each}}>{{#if iconHtml}}{{{iconHtml}}} {{/if}}{{#if html}}{{{html}}}{{else}}{{translate label scope=../scope}}{{/if}}</a></li>
                        {{/each}}
                    </ul>
                </div>
                {{/if}}
            </div>
        </div>
    </div>
    <div class="search-container">{{{search}}}</div>
</div>

