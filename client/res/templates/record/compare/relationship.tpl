<tr class="panel-title"><td colspan="{{mergingColumnLength}}"><span class="panel-title-text">{{translate name category='fields' scope=scope}}</span></td></tr>
{{#if hasToManyRecords }}
   <tr><td colspan="{{mergingColumnLength}}"> {{hasManyRecordsMessage}}</td></tr>
{{else}}
{{#unless tableRows }}
<tr><td colspan="{{mergingColumnLength}}">No Data</td></tr>
{{/unless}}
{{#each tableRows}}
<tr class="list-row {{#unless @first}}{{class}}{{/unless}}">
    <td class="cell l-200" data-field="name">
        <div class="field-name" title="{{title}}">
            {{{label}}}{{#if isRequired}}<sup><i class="ph ph-asterisk"></i></sup>{{/if}}
        </div>
    </td>
    {{#each entityValueKeys}}
        {{#if ../../merging}}
            <td></td>
        {{/if}}
    <td class="cell text-center">
        <div class="field" data-field="{{key}}" >
            {{#if key}}
            {{{var key ../../this }}}
            {{/if}}
        </div>
    </td>
    {{/each}}
</tr>
{{/each}}
{{/if}}