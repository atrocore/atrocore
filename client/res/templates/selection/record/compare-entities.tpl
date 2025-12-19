<div class="detail compare-entities"  id="{{id}}" style="position: relative">
    {{#if showOverlay }}
        <div class="overlay shimmer-container">
            <div class="overlay-logo">
                {{#if overlayLogo}}
                    <img src="{{overlayLogo}}" alt="">
                {{/if}}
            </div>
        </div>
    {{/if}}
    <div class="row">
        <div class="fields-compare-panel col-md-12">
            <div class="compare-panel list col-md-12">
                <div class="panel panel-default panel-entities" data-name="entities">
                    <div class="panel-body">
                        <div class="list-container">
                            <div class="list">
                                <table class="table full-table table-fixed table-scrolled table-bordered">
                                    <colgroup>
                                        {{#each columns}}
                                            <col class="col-min-width" style="width: {{../size}}%">
                                        {{/each}}
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        {{#each columns}}
                                        <th  style="position: relative; padding-right: 65px; padding-left: 25px!important;" data-id="{{id}}">
                                            {{{name}}}
                                            <div class="pull-right inline-actions hidden " style="position: absolute; display: flex; justify-content: end; top: 10px; right: 2px;">
                                                <a href="javascript:" class="swap-entity" title="Replace entity" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}" style="padding: 0 8px">
                                                    <i class="ph ph-swap"></i>
                                                </a>
                                                <a href="javascript:" class="pull-right remove-entity" style="padding-right: 8px" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}"  title="Remove entity" >
                                                    <i class="ph ph-trash-simple"></i>
                                                </a>
                                            </div>
                                        </th>
                                        {{/each}}
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr class="list-row">
                                        {{#each columns}}
                                        <td data-id="{{id}}" style="position:relative">
                                            <div class="record-content"> {{translate 'Loading...'}}</div>
                                            <div class="bottom-layout-bottoms " style="opacity: 0">
                                                <div class="layout-editor-container selection">
                                                </div>
                                                <div class="layout-editor-container relations">
                                                </div>
                                            </div>
                                        </td>
                                        {{/each}}

                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
