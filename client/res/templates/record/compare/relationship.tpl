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
        {{#each relationshipsFields}}
        <tr class="list-row" >
            <td class="cell">{{translate field scope=../scope category='fields'}}</td>
            {{#if currentViewKeys }}
            {{#each currentViewKeys}}
            <td class="cell text-center" data-field="{{key}}" style="min-width: {{../../minWidth}}px">
                {{{var key ../../this }}}
            </td>
            {{/each}}
            {{else}}
            <td class="cell"></td>
            {{/if}}
            {{#each othersModelsKeyPerInstances}}
            {{#if this }}
            {{#each this }}
            <td class="cell text-center" data-field="{{key}}" style="min-width: {{../../../minWidth}}px">
                {{{var key ../../../this}}}
            </td>
            {{/each}}
            {{else}}
                <td class="cell"></td>
            {{/if}}
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
    <div class="panel-scroll hidden" style="display: block;"><div></div></div>
</div>

<style>

</style>