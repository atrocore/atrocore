<div class="detail compare-records" data-mode="{{#if merging}}merge{{else}}compare{{/if}}" id="{{id}}" style="position: relative">
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
                <div class="list-container">
                    <div class="list">
                        <table class="table full-table table-fixed table-scrolled table-bordered">
                            <colgroup>
                                {{#each columns}}
                                    {{#if isFirst }}
                                        <col style="width: 250px;">
                                    {{else}}
                                        {{#if ../merging}}
                                            <col style="width: 50px;">
                                        {{/if}}
                                        <col class="col-min-width">
                                    {{/if}}
                                {{/each}}
                            </colgroup>
                            <thead>
                            <tr>
                                {{#each columns}}
                                    {{#unless isFirst }}
                                        {{#if ../merging }}
                                            <th class="merge-radio">
                                                <div class="center-child" style="width: 100%">
                                                    {{#unless ../hideCheckAll}}
                                                        <input type="radio" disabled="disabled" name="check-all" value="{{id}}" data-id="{{id}}">
                                                    {{/unless}}
                                                </div>
                                            </th>
                                        {{/if}}
                                    {{/unless}}
                                    <th class="text-center {{#unless isFirst}} inline-actions {{/unless}}">
                                        {{{name}}}
                                        {{#if _error}}
                                            <br>
                                            <span class="danger"> ({{_error}})</span>
                                        {{/if}}
                                        {{#unless isFirst}}
                                            <div class="pull-right inline-actions">
                                                <a href="javascript:" data-toggle="dropdown"><i class="ph ph-dots-three-vertical"></i></a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a href="javascript:" class="swap-entity" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}">
                                                            <i class="ph ph-swap"></i> <span>{{translate 'replaceItem' scope='Global' categories='labels'}}</span>
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="javascript:" class="remove-entity" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}">
                                                            <i class="ph ph-trash-simple"></i> <span>{{translate 'removeItem' scope='Global' categories='labels'}}</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        {{/unless}}
                                    </th>
                                {{/each}}
                            </tr>
                            </thead>
                            {{#each fieldPanels}}
                                <tbody class="panel-{{name}}" id="panel-{{name}}" data-name="{{name}}">
                                <tr class="panel-title">
                                    <td colspan="{{../columnLength}}">
                                        <span class="panel-title-text">{{title}}</span>
                                        {{#if hasLayoutEditor}}
                                            <span class="layout-editor-container pull-right"></span>
                                        {{/if}}
                                    </td>
                                </tr>
                                <tr class="list-row">
                                    <td class="cell" colspan="{{../columnLength}}">{{translate 'Loading...'}}</td>
                                </tr>
                                </tbody>
                            {{/each}}
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
