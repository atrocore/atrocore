<div class="detail" id="{{id}}">
    {{#unless buttonsDisabled}}
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
            {{#if dropdownItemList}}
            <button type="button" class="btn btn-default dropdown-toggle dropdown-item-list-button{{#if dropdownItemListEmpty}} hidden{{/if}}" data-toggle="dropdown">
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu pull-left">
                {{#each dropdownItemList}}
                <li class="{{#if hidden}}hidden{{/if}}"><a href="javascript:" class="action" data-action="{{name}}" {{#if id}}data-id="{{id}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{translate label scope=../../entityType}}{{/if}}</a></li>
                {{/each}}
            </ul>
            {{/if}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        {{#if navigateButtonsEnabled}}
        <div class="pull-right">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-default btn-icon action {{#unless previousButtonEnabled}} disabled{{/unless}}" data-action="previous" title="{{translate 'Previous Entry'}}">
                    <span class="fas fa-chevron-left"></span>
                </button>
                <button type="button" class="btn btn-default btn-icon action {{#unless nextButtonEnabled}} disabled{{/unless}}" data-action="next" title="{{translate 'Next Entry'}}">
                    <span class="fas fa-chevron-right"></span>
                </button>
            </div>
        </div>
        {{/if}}
        <div class="clearfix"></div>
    </div>
    <div class="detail-button-container button-container edit-buttons hidden clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonEditList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
            {{#if dropdownEditItemList}}
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu pull-left">
                {{#each dropdownEditItemList}}
                <li class="{{#if hidden}}hidden{{/if}}"><a href="javascript:" class="action" data-action="{{name}}">{{#if html}}{{{html}}}{{else}}{{translate label scope=../../entityType}}{{/if}}</a></li>
                {{/each}}
            </ul>
            {{/if}}
        </div>
        <div class="panel-navigation panel-right pull-left">{{{panelEditNavigation}}}</div>
    </div>
    {{/unless}}


    <div class="row">
        {{#if overviewFilters.length}}
        <div class="col-lg-12 overview-filters-container">
            {{#each overviewFilters}}
            <div class="cell filter-cell" data-name="{{this}}">
                <div class="field" data-name="{{this}}">
                    {{{var this ../this}}}
                </div>
            </div>
            {{/each}}
        </div>
        {{/if}}
        {{#if isTreePanel}}<div class="catalog-tree-panel col-lg-3">{{{treePanel}}}</div>{{/if}}
        <div class="overview {{#if isWide}}col-md-12{{else}}{{#if isSmall}}col-md-7{{else}}{{#if side}}col-md-8{{else}}col-md-12{{/if}}{{/if}}{{/if}}">
            <div class="middle">{{{middle}}}</div>
            <div class="extra">{{{extra}}}</div>
            <div class="bottom">{{{bottom}}}</div>
        </div>
        {{#if side}}
        <div class="side {{#if isWide}} col-md-12{{else}}{{#if isSmall}} col-md-5{{else}} col-md-3{{/if}}{{/if}}">
            {{{side}}}
        </div>
        {{/if}}
    </div>
</div>
