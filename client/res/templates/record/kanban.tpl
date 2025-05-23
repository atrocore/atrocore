
{{#if topBar}}
<div class="list-buttons-container">
    <div class="filter-container"></div>
    <div class="counters-container">
        {{#if displayTotalCount}}
            <div class="text-muted total-count">{{translate 'Shown'}}: <span class="shown-count-span">{{collection.length}}</span><span class="pipeline">|</span>{{translate 'Total'}}: <span class="total-count-span">{{collection.total}}</span></div>
        {{/if}}
    </div>

    {{#each buttonList}}
        {{button name scope=../../scope label=label style=style}}
    {{/each}}
</div>
{{/if}}

<div class="list-kanban" style="min-width: {{minTableWidthPx}}px">
    <div class="kanban-head-container">
    <table class="kanban-head">
        <thead>
            <tr class="kanban-row">
                {{#each groupDataList}}
                <th data-name="{{name}}" class="group-header">
                    <div><span class="kanban-group-label">{{label}}</span></div>
                </th>
                {{/each}}
            </tr>
        </thead>
    </table>
    </div>
    <div class="kanban-columns-container">
    <table class="kanban-columns">
        {{#unless isEmptyList}}
        <tbody>
            <tr class="kanban-row">
                {{#each groupDataList}}
                <td class="group-column" data-name="{{name}}">
                    <div>
                        <div class="group-column-list" data-name="{{name}}">
                            {{#each dataList}}
                            <div class="item" data-id="{{id}}">{{{var key ../../this}}}</div>
                            {{/each}}
                        </div>
                        <div class="show-more">
                            <a data-action="groupShowMore" data-name="{{name}}" title="{{translate 'Show more'}}" class="{{#unless hasShowMore}}hidden {{/unless}}btn btn-link btn-sm"><i class="ph ph-dots-three"></i></a>
                        </div>
                    </div>
                </td>
                {{/each}}
            </tr>
        </tbody>
        {{/unless}}
    </table>
    </div>
</div>


{{#if isEmptyList}}
<div class="margin-top">
{{translate 'No Data'}}
</div>
{{/if}}
