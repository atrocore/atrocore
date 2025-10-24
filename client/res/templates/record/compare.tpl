<div class="detail compare-records" id="{{id}}">
    <div class="row">
        <div class="fields-compare-panel">
            {{#each fieldPanels }}
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
                                <table class="table full-table table-striped table-fixed table-scrolled table-bordered">
                                    <thead>
                                    <tr>
                                        {{#each ../columns}}
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
                                        <td class="cell" colspan="{{../columnLength}}"> {{translate 'Loading...'}}</td>
                                    </tr>
                                    <tr class="list-row">
                                        <td class="cell" colspan="{{../columnLength}}"> {{translate 'Loading...'}}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{/each}}
        </div>
    </div>
    <div class="row">
        <div class="compare-panel  list col-md-12" data-name="relationshipsPanels"></div>
    </div>
</div>
