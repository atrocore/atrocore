{{#if collection.models.length}}

{{#if topBar}}
<div class="list-buttons-container clearfix">
    {{#if paginationTop}}
    <div>
        {{{pagination}}}
    </div>
    {{/if}}

    {{#if checkboxes}}
    {{#if massActionList}}
    <div class="btn-group actions">
        <button type="button" class="btn btn-default dropdown-toggle actions-button" data-toggle="dropdown" disabled>
        {{translate 'Actions'}}
        <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            {{#each massActionList}}
            {{#if action}}
               <li><a href="javascript:" data-action="{{action}}" data-id="{{id}}" class='mass-action'>{{label}}</a></li>
            {{else}}
               <li><a href="javascript:" data-action="{{./this}}" class='mass-action'>{{translate this category="massActions" scope=../scope}}</a></li>
            {{/if}}
            {{/each}}
        </ul>
    </div>
    {{/if}}
    {{/if}}

    {{#if displayTotalCount}}
    <div class="text-muted total-count">{{translate 'Shown'}}: <span class="shown-count-span">{{collection.length}}</span><span class="pipeline">|</span>{{translate 'Total'}}: <span class="total-count-span">{{collection.total}}</span></div>
    {{/if}}

    {{#each buttonList}}
        {{button name scope=../../scope label=label style=style}}
    {{/each}}
</div>
{{/if}}

<div class="list">
    <table class="table fixed-header-table hidden">
        {{#if header}}
        <thead>
            <tr>
                {{#if checkboxes}}
                <th width="40" data-name="r-checkbox">
                    <span class="select-all-container"><input type="checkbox" class="select-all fixed"></span>
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
                                    {{translate this.name scope=../../../collection.name category='fields'}}
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
                        {{else}}
                            {{translate this.name scope=../../../collection.name category='fields'}}
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
                    <span class="select-all-container"><input type="checkbox" class="select-all"></span>
                </th>
                {{/if}}
                {{#each headerDefs}}
                <th {{#if width}} width="{{width}}"{{/if}}{{#if align}} style="text-align: {{align}};"{{/if}}>
                    <div>
                        {{#if this.sortable}}
                            <a href="javascript:" class="sort" data-name="{{this.name}}">{{#if this.hasCustomLabel}}{{this.customLabel}}{{else}}{{translate this.name scope=../../../collection.name category='fields'}}{{/if}}</a>
                            {{#if this.sorted}}{{#if this.asc}}<span>&#8593;</span>{{else}}<span>&#8595;</span>{{/if}}{{/if}}
                        {{else}}
                            {{#if this.hasCustomLabel}}
                                {{this.customLabel}}
                            {{else}}
                                {{translate this.name scope=../../../collection.name category='fields'}}
                            {{/if}}
                        {{/if}}
                    </div>
                </th>
                {{/each}}
            </tr>
        </thead>
        {{/if}}
        <tbody>
        {{#each rowList}}
            <tr data-id="{{./this}}" class="list-row">
            {{{var this ../this}}}
            </tr>
        {{/each}}
        </tbody>
    </table>
    {{#unless paginationEnabled}}
    {{#if showMoreEnabled}}
    <div class="show-more{{#unless showMoreActive}} hide{{/unless}}">
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
{{/if}}
