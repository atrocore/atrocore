<div class="list custom-compare-relationship">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered {{#if showBorders}} bottom-border-black {{/if}}">
        <colgroup>
            {{#each columns}}
            {{#if isFirst }}
            <col style="width: 250px;">
            {{else}}
            <col class="col-min-width">
            {{/if}}
            {{/each}}
        </colgroup>
        <thead>
            <tr>
            {{#each columns}}
            <th colspan="{{itemColumnCount}}" class="text-center">
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
        </tr>
        </thead>
        <tbody>
        {{#if hasToManyRecords }}
           <tr><td colspan="{{columnLength}}"> {{hasManyRecordsMessage}}</td></tr>
        {{else}}
        {{#unless tableRows }}
        <tr> <td  colspan="{{columnLength}}"> No Data</td></tr>
        {{/unless}}
        {{#each tableRows}}
        <tr class="list-row  {{class}}" >
            <td class="cell l-200" data-field="name" title="{{title}}">
                {{#if isField}}
                <div data-key="{{key}}" style="display:flex; flex-direction: column; align-items: baseline">
                    {{{var key ../this}}}
                </div>
                {{else}}
                {{{label }}}
                {{/if}}
            </td>
            {{#each entityValueKeys}}
            <td class="cell text-center" data-field="{{key}}" style="min-width: {{../../minWidth}}px">
                {{#if key}}
                    {{{var key ../../this }}}
                {{/if}}
            </td>
            {{/each}}
        </tr>
        {{/each}}
        {{/if}}
        </tbody>
    </table>
    <div class="panel-scroll hidden" style="display: block;"><div></div></div>
</div>
