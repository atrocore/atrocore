{{#unless showNoData}}
<div class="list" {{#if listInlineEditModeEnabled}} data-editable="true"{{/if}}>
    <table class="table full-table"{{#if resizable}} data-resizable="true"{{/if}}>
        {{#if header}}
        <thead>
            <tr>
                {{#if checkboxes}}
                <th width="40" data-name="r-checkbox">
                    {{#if allowSelectAllResult}}
                        <span class="select-all-container"><input type="checkbox" class="select-all" {{#if disableSelectAllResult}}disabled{{/if}}></span>
                    {{/if}}
                </th>
                {{/if}}
                {{#each headerDefs}}
                <th {{#if width}} width="{{width}}"{{/if}}{{#if align}} style="text-align: {{align}};"{{/if}}{{#if this.resizeSpacer}} class="table-spacer"{{/if}}{{#if this.name}} data-name="{{this.name}}"{{/if}}>
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
    <div class="no-data-container">{{translate 'No Data'}}</div>
{{/unless}}
