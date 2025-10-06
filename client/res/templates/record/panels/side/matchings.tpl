<div class="row">
    <div class="cell col-sm-12 form-group">
        {{#if matchesList}}{{#each matchesList}}
           <label class="control-label"><span class="label-text" data-action="findMatches" data-name="{{name}}" style="cursor: pointer" title="{{translate 'findMatches'}}">{{label}}</span></label>
           <div class="list-container" data-name="{{name}}">{{{name}}}</div>                   
        {{/each}}{{else}}
        <div>...</div>
        {{/if}}
    </div>
</div>