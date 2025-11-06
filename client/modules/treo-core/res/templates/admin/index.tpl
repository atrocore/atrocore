<div class="content-wrapper">
    <aside class="tree-panel-anchor"></aside>
    <div class="admin-page">
        <div class="page-header"></div>
        <div class="admin-content">
            <div class="row">
                <div class="col-md-7">
                    <div class="admin-tables-container">
                        {{#each panelDataList}}
                        <div>
                            <h4>{{translate label scope='Admin'}}</h4>
                            <table class="table table-bordered table-admin-panel" data-name="{{name}}">
                                {{#each itemList}}
                                <tr>
                                    <td>
                                        <a href="{{url}}"{{#if tooltip}} title="{{tooltip}}"{{/if}}>{{translate label scope='Admin' category='labels'}}{{#if hasWarning}}<i class="ph ph-warning-circle warning-icon"{{#if warningText}} title="{{warningText}}"{{/if}}></i>{{/if}}</a>
                                    </td>
                                    <td>{{translate description scope='Admin' category='descriptions'}}</td>
                                </tr>
                                {{/each}}
                            </table>
                        </div>
                        {{/each}}
                    </div>
                </div>
                <div class="col-md-5 admin-right-column">
                    <div class="notifications-panel-container">{{{notificationsPanel}}}</div>
                </div>
            </div>
        </div>
    </div>

</div>
