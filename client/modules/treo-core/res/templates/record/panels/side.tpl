{{#if fieldList.length}}
<div class="col-sm-6">
    {{#each fieldList}}
    <div class="cell form-group col-sm-12{{#if hidden}} hidden-cell{{/if}}" data-name="{{name}}">
        <label class="control-label{{#if hidden}} hidden{{/if}}" data-name="{{name}}"><span class="label-text">{{translate name scope=../scope category='fields'}}</span></label>
        <div class="field{{#if hidden}} hidden{{/if}}" data-name="{{name}}">
        {{{var viewKey ../this}}}
        </div>
    </div>
    {{/each}}
</div>
{{/if}}
