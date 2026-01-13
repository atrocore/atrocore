{{#each fieldList}}
    <tr class="panel-title">
        <td colspan="{{#if ../merging}}{{mergeRowLength}}{{else}}{{rowLength}}{{/if}}">
            <span class="panel-title-text">{{translate ../panelTitle}}</span>
            {{#if ../hasLayoutEditor}}
                <span class="layout-editor-container"></span>
            {{/if}}
        </td>
    </tr>
    {{#if isInGroup }}
        <tr>
            <td colspan="{{rowLength}}"><a href="#AttributeGroup/view/{{id}}" target="_blank" class="attribute-group">{{name}}</a></td>
        </tr>
   {{/if}}
    {{#each fieldListInGroup }}
        <tr class="list-row {{#if different}}danger{{/if}}" data-field="{{field}}">
            <td class="cell">
               <div class="field-name">
                   {{#if attributeId}}
                   <a href="#Attribute/view/{{attributeId}}" target="_blank" class="attribute" title="{{translate label scope=../../scope category='fields'}}">  {{translate label scope=../../scope category='fields'}}{{#if required }}*{{/if}} </a>
                   {{else}}
                   <span title="{{translate label scope=../../scope category='fields'}}">{{translate label scope=../../scope category='fields'}}{{#if required }} <sup><i class="ph ph-asterisk"></i></sup>{{/if}}</span>
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

