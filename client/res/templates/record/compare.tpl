<div class="detail" id="{{id}}">
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        <div class="clearfix"></div>
    </div>

    <div class="row">
        <div class="compare-panel  list col-md-12" data-name="fieldsPanels">
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
                    <th width="25"></th>
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
        max-width: 100px;
    }

    .file-link {
        white-space: normal;
    }

    .compare-panel table tbody tr.danger {
        border-left: 2px solid red;
    }

    .compare-panel table tbody tr.danger > td {
        background-color: transparent;
    }

    th span.danger {
        color: red;
    }

    [data-name="relationshipsPanels"] .panel-body {
        padding: 15px 0 0 0 !important;
    }
</style>
