<div class="list" style="overflow-x: clip">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered" style=" table-layout: auto">
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
        {{#unless tableRows }}
         <tr> <td  colspan="{{itemColumnCount}}"> No Data</td></tr>
        {{/unless}}
        {{#each tableRows}}
        <tr class="list-row" >
            <td class="cell" data-field="name" style="min-width: {{../minWidth}}px">
                {{#if isField}}
                <div data-key="{{key}}">
                    {{{var key ../this}}}
                </div>
                {{else}}
                {{{label }}}
                {{/if}}
            </td>
            {{#each entityValueKeys}}
            <td class="cell text-center" data-field="{{key}}" style="min-width: {{../../minWidth}}px">
                {{{var key ../../this }}}
            </td>
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
    <div class="panel-scroll hidden" style="display: block;"><div></div></div>
</div>

<style>

</style>