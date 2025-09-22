<div class="page-header dashboard-header">
    {{#if displayTitle}}
        <h3>{{translate 'Dashboard' category='scopeNames'}}</h3>
    {{/if}}
    <div class="controls">
        {{#ifNotEqual dashboardLayout.length 1}}
            <div class="btn-group dashboard-tabs">
                {{#each dashboardLayout}}
                    <button class="btn btn-default{{#ifEqual @index ../currentTab}} active{{/ifEqual}}" data-action="selectTab" data-tab="{{@index}}">{{name}}</button>
                {{/each}}
            </div>
            <div class="dashboard-selectbox">
                <select class="form-control" data-action="selectTab">
                    {{#each dashboardLayout}}
                        <option value="{{@index}}" {{#ifEqual @index ../currentTab}}selected{{/ifEqual}}>{{name}}</option>
                    {{/each}}
                </select>
            </div>
        {{/ifNotEqual}}
        {{#unless layoutReadOnly}}
            <button data-action="reset">{{translate 'Reset to Default'}}</button>
            <div class="button-group dashboard-buttons">
                <button data-action="editTabs" title="{{translate 'Edit Dashboard'}}"><i class="ph ph-pencil-simple"></i></button>
                <button data-action="addDashlet" title="{{translate 'Add Dashlet'}}"><i class="ph ph-plus"></i></button>
            </div>
        {{/unless}}
    </div>
</div>
<div class="dashlets grid-stack grid-stack-4 row">{{{dashlets}}}</div>
