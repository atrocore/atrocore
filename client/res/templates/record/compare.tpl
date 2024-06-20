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

        </div>

        <div class="compare-panel  list col-md-12" data-name="relationshipsPanels">

        </div>

        <div class="compare-panel  list col-md-12" data-name="attributesPanels">

        </div>
    </div>
</div>

<style>
    .compare-panel{
        margin-bottom: 80px;
    }
</style>