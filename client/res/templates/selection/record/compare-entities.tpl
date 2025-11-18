<div class="detail compare-entities"  id="{{id}}" style="position: relative">
    {{#if showOverlay }}
    <div class="overlay"></div>
    {{/if}}
    <div class="row">
        <div class="fields-compare-panel col-md-12">
            <div class="compare-panel list col-md-12">
                <div class="panel panel-default panel-{{name}}" data-name="{{name}}">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            {{title}}
                        </h4>
                    </div>

                    <div class="panel-body">
                        <div class="list-container">
                            <div class="list">
                                <table class="table full-table table-fixed table-scrolled table-bordered">
                                    <colgroup>
                                        {{#each columns}}
                                            <col class="col-min-width">
                                        {{/each}}
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        {{#each columns}}
                                        <th class="text-center">
                                            {{{name}}}
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
                                        {{#each columns}}
                                        <td  data-id="{{id}}"> {{translate 'Loading...'}}</td>
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
