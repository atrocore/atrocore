{{#if topBar}}
<div class="list-buttons-container">
    {{#if paginationTop}}
    <div>
        {{{pagination}}}
    </div>
    {{/if}}

    {{#if checkboxes}}
        {{#if massActionList}}
            <div class="btn-group actions">
                <button type="button" class="btn btn-default dropdown-toggle actions-button" data-toggle="dropdown" disabled>
                    {{translate 'Actions'}} <i class="ph ph-caret-down"></i>
                </button>
                <ul class="dropdown-menu">
                    {{#each massActionList}}
                    {{#if action}}
                    <li><a href="javascript:" data-action="{{action}}" data-id="{{id}}" class='mass-action'>{{label}}</a></li>
                    {{else if divider}}
                        <li class="divider"></li>
                    {{else}}
                    <li><a href="javascript:" data-action="{{./this}}" class='mass-action'>{{translate this category="massActions" scope=../scope}}</a></li>
                    {{/if}}
                    {{/each}}
                </ul>
            </div>
        {{/if}}
    {{/if}}

    <div class="filter-container"></div>

    <div class="counters-container">
        <div class="text-muted selected-count hidden">{{translate 'Selected'}}: <span class="selected-count-span">0</span></div>

        {{#if displayTotalCount}}
            <div class="text-muted total-count {{#if totalLoading}} hidden {{/if}}">{{translate 'Shown'}}: <span class="shown-count-span">{{collection.length}}</span><span class="pipeline">|</span>{{translate 'Total'}}: <span class="total-count-span">{{collection.total}}</span></div>
            <img class="preloader {{#unless totalLoading}} hidden {{/unless}}" style="float:right;height:12px;" src="client/img/atro-loader.svg" />
        {{/if}}
    </div>

    {{#each buttonList}}
        {{button name scope=../scope label=label style=style}}
    {{/each}}
</div>
{{/if}}

{{#unless showNoData}}
<div class="list">
    <table class="table fixed-header-table hidden">
        {{#if header}}
        <thead>
            <tr>
                {{#if checkboxes}}
                <th width="40" data-name="r-checkbox">
                    {{#if allowSelectAllResult}}
                        <span class="select-all-container"><input type="checkbox" class="select-all fixed" {{#unless collection.models.length}}disabled{{/unless}}></span>
                    {{/if}}
                </th>
                {{/if}}
                {{#each headerDefs}}
                <th {{#if align}} style="text-align: {{align}};"{{/if}}>
                    {{#if this.sortable}}
                        <div>
                            <a href="javascript:" class="sort" data-name="{{this.name}}">
                                {{#if this.hasCustomLabel}}
                                    {{this.customLabel}}
                                {{else}}
                                    {{this.label}}
                                {{/if}}
                            </a>
                            {{#if this.sorted}}
                                {{#if this.asc}}
                                    <span>&#8593;</span>
                                {{else}}
                                    <span>&#8595;</span>
                                {{/if}}
                        {{/if}}
                        </div>
                    {{else}}
                        {{#if this.hasCustomLabel}}
                            {{this.customLabel}}
                        {{else if this.layoutEditor}}
                            <div class="layout-editor-container"></div>
                        {{else}}
                            {{this.label}}
                        {{/if}}
                    {{/if}}
                </th>
                {{/each}}
            </tr>
        </thead>
        {{/if}}
    </table>
    <table class="table full-table">
        {{#if header}}
        <thead>
            <tr>
                {{#if checkboxes}}
                <th width="40" data-name="r-checkbox">
                    {{#if allowSelectAllResult}}
                        <span class="select-all-container"><input type="checkbox" class="select-all" {{#unless collection.models.length}}disabled{{/unless}}></span>
                    {{/if}}
                </th>
                {{/if}}
                {{#each headerDefs}}
                <th {{#if width}} width="{{width}}"{{/if}}{{#if align}} style="text-align: {{align}};"{{/if}}>
                    <div>
                        {{#if this.sortable}}
                            <a href="javascript:" class="sort" data-name="{{this.name}}">{{#if this.hasCustomLabel}}{{this.customLabel}}{{else}}{{this.label}}{{/if}}</a>
                            {{#if this.sorted}}{{#if this.asc}}<span>&#8593;</span>{{else}}<span>&#8595;</span>{{/if}}{{/if}}
                        {{else}}
                            {{#if this.hasCustomLabel}}
                                {{this.customLabel}}
                            {{else if this.layoutEditor}}
                            <div class="layout-editor-container"></div>
                            {{else}}
                               {{this.label}}
                            {{/if}}
                        {{/if}}
                    </div>
                </th>
                {{/each}}
            </tr>
        </thead>
        {{/if}}
        <tbody>
            {{#if collection.models.length}}
                {{#each rowList}}
                    <tr data-id="{{./this}}" class="list-row">
                        {{{var this ../this}}}
                    </tr>
                {{/each}}
            {{/if}}
        </tbody>
    </table>
    {{#unless paginationEnabled}}
    {{#if showMoreEnabled}}
    <div class="show-more{{#unless showMoreActive}} hidden{{/unless}}">
        <a type="button" href="javascript:" class="btn btn-default btn-block" data-action="showMore" {{#if showCount}}title="{{translate 'Total'}}: {{collection.total}}"{{/if}}>
            <span class="more-label">{{countLabel}}</span>
        </a>
        <img class="preloader" style="display:none;height:12px;margin-top: 5px" src="client/img/atro-loader.svg" />
    </div>
    {{/if}}
    {{/unless}}
</div>

{{#if bottomBar}}
<div>
{{#if paginationBottom}} {{{pagination}}} {{/if}}
</div>
{{/if}}

{{else}}
    {{translate 'No Data'}}
{{/unless}}
