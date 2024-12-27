<div class="list">
    <table class="table full-table table-fixed table-striped table-scrolled table-bordered">
        <colgroup>
            {{#each columns}}
            {{#if isFirst }}
            <col style="width: 250px;">
            {{else}}
            {{#if ../merging}}
            <col style="width: 50px">
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
                <th>
                   <div class="center-child">
                       <input type="radio" name="check-all" value="{{id}}" data-id="{{id}}">
                   </div>
                </th>
            {{/if}}
            {{/unless}}
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
        {{#each fieldList}}
        <tr class="list-row {{#if  different}} danger {{/if}}" data-field="{{field}}">
            <td class="cell ">{{translate label scope=../scope category='fields'}}</td>
            {{#each fieldValueRows}}
            {{#if ../../merging}}
            <td>
               <div class="center-child" >
                   <input type="radio" name="{{../field}}" value="{{id}}" data-id="{{id}}" data-key="{{key}}" class="field-radio">
               </div>
            </td>
            {{/if}}
            <td class="cell  {{#unless shouldNotCenter}} text-center {{/unless}}">
                <div class="{{class}}  field">Loading...</div>
            </td>
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
    <div class="panel-scroll hidden" style="display: block;">
        <div></div>
    </div>
</div>
<style>
    .hidden-cell {
        display: none !important;
    }

    .compare-panel th:first-child {
        text-align: left !important;
    }
</style>

