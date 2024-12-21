<div class="panel panel-default panel-overviewPanels" >
    <div class="panel-heading">
        <h4 class="panel-title">
            Overviews
        </h4>
    </div>

    <div class="panel-body">
        <div class="list-container" data-panel="fields-overviews">
            <div class="list">
                <table class="table full-table table-fixed table-striped table-scrolled table-bordered">
                    <thead>
                    <tr>
                        {{#each columns}}
                        <th class="text-center">
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
                    {{#each fieldList}}
                    {{#each fields}}
                    <tr class="list-row {{#if  different}} danger {{/if}}" data-field="{{field}}">
                        <td class="cell l-200" >{{translate label scope=../../scope category='fields'}}</td>
                        <td class="cell current text-center">
                            {{{var current ../../this}}}
                        </td>
                        {{#each others}}
                        <td class="cell other{{index}} text-center">
                            {{{var other ../../../this}}}
                        </td>
                        {{/each}}
                    </tr>
                    {{/each}}
                    {{/each}}
                    </tbody>
                </table>
                <div class="panel-scroll hidden" style="display: block;"><div></div></div>
            </div>
        </div>
</div>
<style>
    .hidden-cell{
        display:none !important;
    }

    .compare-panel th:first-child{
       text-align: left !important;
    }
</style>

