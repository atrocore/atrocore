<div class="overview col-md-12">
    <div class="row">
        <div class="field">
            <div class="button-container clearfix">
                <button class="btn btn-default btn-icon" data-action="editTabs" title="{{translate 'Edit Dashboard'}}"><i class="ph ph-pencil-simple"></i></button>
                <button class="btn btn-default btn-icon" data-action="addDashlet" title="{{translate 'Add Dashlet'}}"><i class="ph ph-plus"></i></button>

                {{#ifNotEqual dashboardLayout.length 1}}
                <div class="btn-group pull-right dashboard-tabs">
                    {{#each dashboardLayout}}
                    <button class="btn btn-default{{#ifEqual @index ../currentTab}} active{{/ifEqual}}" data-action="selectTab" data-tab="{{@index}}">{{name}}</button>
                    {{/each}}
                </div>
                {{/ifNotEqual}}
            </div>


            <div class="grid-stack grid-stack-4"></div>
        </div>
    </div>
</div>