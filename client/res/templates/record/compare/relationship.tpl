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
            <td class="cell" data-field="{{key}}" style="min-width: 200px">
                {{{var key ../../this }}}
            </td>
            {{/each}}
            {{else}}
            <td class="cell"></td>
            {{/if}}
            {{#each othersModelsKeyPerInstances}}
            {{#if this }}
            {{#each this }}
            <td class="cell" data-field="{{key}}" style="min-width: 200px">
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
    .border-left { border-left: 2px solid transparent !important; }
    .border-right { border-right: 2px solid transparent !important;}
</style>