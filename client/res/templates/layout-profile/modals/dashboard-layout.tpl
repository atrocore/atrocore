<div class="overview col-md-12">
    <div class="row">
        <div class="field">
            <div class="button-container clearfix">
                <button class="btn btn-default btn-icon" data-action="editTabs" title="{{translate 'Edit Dashboard'}}"><svg class="icon icon-small"><use href="client/img/icons/icons.svg#pencil-alt"></use></svg></button>
                <button class="btn btn-default btn-icon" data-action="addDashlet" title="{{translate 'Add Dashlet'}}"><svg class="icon icon-small"><use href="client/img/icons/icons.svg#plus"></use></svg></button>

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