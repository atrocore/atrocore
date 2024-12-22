<div class="detail" id="{{id}}">
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        <div class="clearfix"></div>
    </div>

    <div class="row">
        <div class="compare-panel list col-md-12">
            <div class="panel panel-default panel-overviewPanels" data-panel="fields-overviews">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        Overviews
                    </h4>
                </div>

                <div class="panel-body">
                    <div class="list-container" >
                        <div class="list" >
                            <table class="table full-table table-striped table-fixed table-scrolled table-bordered">
                                <thead>
                                <tr>
                                    {{#each columns}}
                                    <th class="text-center">
                                        {{#if link}}
                                        <a href="#{{../scope}}/view/{{name}}"> {{label}}</a>
                                        {{else}}
                                        {{name}}
                                        {{/if}}
                                        {{#if _error}}
                                        <br>
                                        <span class="danger"> ({{_error}})</span>
                                        {{/if}}
                                    </th>
                                    {{/each}}
                                </tr>
                                </thead>
                                <tbody>
                                <tr class="list-row">
                                    <td class="cell" colspan="{{columnLength}}"> {{translate 'Loading...'}}</td>
                                </tr>
                                <tr class="list-row">
                                    <td class="cell" colspan="{{columnLength}}"> {{translate 'Loading...'}}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="compare-panel  list col-md-12" data-name="relationshipsPanels">

        </div>
    </div>
</div>
<style>
    .hidden-cell {
        display: none !important;
    }

    thead tr th[colspan="*"] {
        text-align: center;
    }

    .compare-panel[data-name='relationshipsPanels'] .panel.panel-default {
        margin-bottom: 50px;
    }

    .compare-panel {
        margin-bottom: 50px;
        background-color: white;
        width: 100%;
    }

    .compare-panel table td, .compare-panel table th {
        max-width: 300px;
    }

    .file-link {
        white-space: normal;
    }

    .compare-panel table tbody tr.danger {
        border-left: 3px solid red;
    }

    .compare-panel table col.col-min-width{
        min-width: 200px;
    }

    .compare-panel table tbody tr.danger td:first-child{
        border-left: 3px solid red;
    }

    .compare-panel table tbody tr.danger > td {
        background-color: transparent;
    }

    th span.danger {
        color: red;
    }

    .compare-panel .panel-body {
        padding: 15px 0 0 0 !important;
    }

    .compare-panel .panel-body div.list {
        overflow-x: auto;
    }

    .compare-panel  .table{
        table-layout: fixed;
    }

    .compare-panel tr td:first-child {
        width: 250px !important;
    }

    .compare-panel tr td:first-child {
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compare-panel td {
        min-width: 150px !important;
    }

    .compare-panel .attachment-preview a {
       display: flex;
        justify-content: center;
    }


    [data-name="relationshipsPanels"] .bottom-border-black tr.strong-border {
       border-top: 3px solid rgba(0,0,0, 0.3);
    }

    [data-name="relationshipsPanels"] table {
       table-layout: auto;
    }

</style>
