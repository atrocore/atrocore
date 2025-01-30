{{#each overviewFilters}}
<div class="cell form-group" data-name="{{name}}">
    <label class="control-label" data-name="{{name}}">{{label}}</label>
   <div class="field" data-name="{{name}}">
        {{{var name ../this}}}
    </div>
</div>
{{/each}}

