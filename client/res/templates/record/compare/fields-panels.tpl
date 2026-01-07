<div class="list">
    <table class="table full-table table-fixed table-striped table-scrolled table-bordered">
        <colgroup>
            {{#each columns}}
            {{#if isFirst }}
            <col style="width: 250px;">
            {{else}}
            {{#if ../merging}}
            <col style="width: 30px">
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
                <th >
                   <div class="center-child">
                       {{#unless ../hideCheckAll}}
                       <input type="radio" disabled="disabled" name="check-all" value="{{id}}" data-id="{{id}}">
                       {{/unless}}
                   </div>
                </th>
            {{/if}}
            {{/unless}}
            <th class="text-center  {{#unless isFirst}} inline-actions {{/unless}}" style="position: relative; {{#unless isFirst}} padding: 0 25px {{/unless}}" title="{{label}}">
                {{{name}}}
                {{#if _error}}
                <br>
                <span class="danger"> ({{_error}})</span>
                {{/if}}
                {{#unless isFirst}}
                    <div class="pull-right inline-actions hidden" style="position: absolute; display: flex; justify-content: end; top: 10px; right: 2px;">
                        <a href="javascript:" class="swap-entity" title="{{translate 'replaceItem' scope='Global' categories='labels'}}" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}" style="padding: 0 5px">
                            <i class="ph ph-swap"></i>
                        </a>
                        <a href="javascript:" class="pull-right remove-entity" data-entity-type="{{entityType}}" data-selection-record-id="{{selectionRecordId}}" data-id="{{id}}"  title="{{translate 'removeItem' scope='Global' categories='labels'}}" >
                            <i class="ph ph-trash-simple"></i>
                        </a>
                    </div>
                {{/unless}}
            </th>
            {{/each}}
        </tr>

        </thead>
        <tbody>
        {{#each fieldList}}
            {{#if isInGroup }}
                <tr>
                    <td colspan="{{rowLength}}"> <a href="#AttributeGroup/view/{{id}}" target="_blank" class="attribute-group">{{name}}</a></td>
                </tr>
           {{/if}}
            {{#each fieldListInGroup }}
                <tr class="list-row {{#if  different}}danger{{/if}}" data-field="{{field}}">
                    <td class="cell " title="{{translate label scope=../../scope category='fields'}}">
                       <div class="field-name">
                           {{#if attributeId}}
                           <a href="#Attribute/view/{{attributeId}}" target="_blank" class="attribute">  {{translate label scope=../../scope category='fields'}}{{#if required }}*{{/if}} </a>
                           {{else}}
                           <span>{{translate label scope=../../scope category='fields'}}{{#if required }}*{{/if}}</span>
                           {{/if}}
                       </div>
                    </td>
                    {{#each fieldValueRows}}
                        {{#if ../../../merging}}
                        <td class="merge-radio">
                           <div class="center-child" >
                               <input type="radio" name="{{../field}}" value="{{id}}" disabled="disabled" data-id="{{id}}" data-key="{{key}}" class="field-radio">
                           </div>
                        </td>
                        {{/if}}
                    <td class="cell {{#if shouldNotCenter}}no-center{{/if}}"  data-name="{{../field}}">
                        <div class="{{class}} field">{{{var key ../../../this}}}</div>
                    </td>
                    {{/each}}
                </tr>
            {{/each}}
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

    a.attribute {
        color: black;
    }

    a.attribute-group {
        font-weight: bold;
    }

    .compare-panel th:first-child {
        text-align: left !important;
    }
</style>

