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
                                        <th data-id="{{id}}">
                                            {{{name}}}
                                            <div class="pull-right inline-actions">
                                                {{{var action ../this}}}
                                            </div>
                                        </th>
                                        {{/each}}
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr class="list-row">
                                        {{#each columns}}
                                        <td data-id="{{id}}" style="position:relative">
                                            <div class="record-content">{{translate 'Loading...'}}</div>
                                            <div class="bottom-layout-bottoms" style="opacity: 0">
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
