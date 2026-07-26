{{#if hasExtraFields}}
<div class="extra-fields-container row" style="padding: 15px 15px 0 15px; border-bottom: 1px solid #e8eced; margin-bottom: 10px;"></div>
{{/if}}
<div class="search-container">{{{search}}}</div>
<div class="list-buttons-container for-table-view"></div>
<div class="list-container for-table-view">{{{list}}}</div>
{{#if hasTree}}
    <div class="list-container for-tree-view">
        <div class="list-buttons-container">
            <div class="filter-container"></div>
            {{#if hasTotalCount}}
                <div class="counters-container">
                    <div class="text-muted total-count">{{translate 'Shown'}}: <span
                            class="shown-count-span">{{totalCount}}</span> | {{translate 'Total'}}: <span
                            class="total-count-span">{{totalCount}}</span></div>
                </div>
            {{/if}}
        </div>
        <div class="records-tree"></div>
    </div>
{{/if}}