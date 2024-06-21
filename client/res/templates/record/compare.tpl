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
            <table class="table full-table table-striped table-fixed table-scrolled table-bordered" >
                <thead>
                <tr>
                    <th>{{ translate 'instance' scope='Synchronization' }}</th>
                    <th>
                        {{translate 'current' scope='Synchronization' category='labels'}}
                    </th>
                    {{#each ../instanceNames}}
                    <th class="text-center">
                        {{name}}
                    </th>
                    {{/each}}
                    <th width="25"></th>
                </tr>
                </thead>
                <tbody>
                <tr class="list-row">
                <td class="cell" colspan="3"> {{translate 'Loading...'}}</td>
                </tr>
                <tr class="list-row" >
                    <td class="cell" colspan="3"> {{translate 'Loading...'}}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="compare-panel  list col-md-12" data-name="relationshipsPanels">

        </div>

        <div class="compare-panel  list col-md-12" data-name="attributesPanels">

        </div>
    </div>
</div>
