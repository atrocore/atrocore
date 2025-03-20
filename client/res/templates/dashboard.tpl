
<div class="page-header dashboard-header">
    <div class="row">
        <div class="col-sm-5">
            {{#if displayTitle}}
            <h3>{{translate 'Dashboard' category='scopeNames'}}</h3>
            {{/if}}
        </div>
        <div class="col-sm-7 clearfix">
            {{#unless layoutReadOnly}}
            <div class="btn-group pull-right dashboard-buttons">
                <button class="btn btn-default " data-action="reset" style="margin: 0 10px">{{translate 'Reset'}}</button>
                <button class="btn btn-default btn-icon" data-action="editTabs" title="{{translate 'Edit Dashboard'}}"><svg class="icon icon-small"><use href="client/img/icons/icons.svg#pencil-alt"></use></svg></button>
                <button class="btn btn-default btn-icon" data-action="addDashlet" title="{{translate 'Add Dashlet'}}"><span class="fas fa-plus"></span></button>
            </div>
            {{/unless}}
            {{#ifNotEqual dashboardLayout.length 1}}
            <div class="btn-group pull-right dashboard-tabs">
                {{#each dashboardLayout}}
                    <button class="btn btn-default{{#ifEqual @index ../currentTab}} active{{/ifEqual}}" data-action="selectTab" data-tab="{{@index}}">{{name}}</button>
                {{/each}}
            </div>
            <div class="pull-right dashboard-selectbox">
                <select class=" form-control" data-action="selectTab">
                    {{#each dashboardLayout}}
                    <option {{#ifEqual @index ../currentTab}}selected{{/ifEqual}}>{{name}}</option>
                    {{/each}}
                </select>
            </div>
            {{/ifNotEqual}}
        </div>
    </div>
</div>
<div class="dashlets grid-stack grid-stack-4 row">{{{dashlets}}}</div>

