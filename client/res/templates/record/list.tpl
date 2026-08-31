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
                            {{#if this.sorted}}{{#if this.asc}}<span><i class="ph ph-arrow-up"></i></span>{{else}}<span><i class="ph ph-arrow-down"></i></span>{{/if}}{{/if}}
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
        {{else}}
            <colgroup>
                {{#if checkboxes}}
                    <col style="width: 40px">
                {{/if}}
                {{#each headerDefs}}
                    <col {{#if width}} style="width: {{width}}"{{/if}}>
                {{/each}}
            </colgroup>
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
</div>
{{#if showMoreEnabled}}
<div class="table-show-more-container"></div>
{{/if}}

{{else}}
    <div class="no-data-container">{{translate 'No Data'}}</div>
{{/unless}}
