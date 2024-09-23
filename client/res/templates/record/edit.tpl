<div class="edit" id="{{id}}">
    {{#unless buttonsDisabled}}
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group" role="group">
            {{#each buttonList}}{{button name scope=../entityType label=label style=style html=html}}{{/each}}
            {{#if dropdownItemList}}
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu pull-left">
                {{#each dropdownItemList}}
                <li><a href="javascript:" class="action" data-action="{{name}}" {{#if id}}data-id="{{id}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../entityType}}{{/if}}</a></li>
                {{/each}}
            </ul>
            {{/if}}
        </div>
    </div>
    {{/unless}}

    <div class="row">
        <div class="overview {{#if isWide}}col-md-12{{else}}{{#if isSmall}}col-md-7{{else}}{{#if side}}col-md-8{{else}}col-md-12{{/if}}{{/if}}{{/if}}">
            <div class="middle">{{{middle}}}</div>
            <div class="extra">{{{extra}}}</div>
            <div class="bottom">{{{bottom}}}</div>
        </div>
        {{#if side}}
        <div class="side {{#if isWide}} col-md-12{{else}}{{#if isSmall}} col-md-5{{else}} col-md-4{{/if}}{{/if}}">
            {{{side}}}
        </div>
        {{/if}}
    </div>
</div>