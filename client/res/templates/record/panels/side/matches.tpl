<div class="row">
    <div class="cell col-sm-12 form-group">
        {{#if matchesList}}{{#each matchesList}}
           <label class="control-label"><span class="label-text" data-action="findMatches" data-name="{{name}}" style="cursor: pointer" title="{{translate 'findMatches'}}">{{label}}</span></label>
           <div class="links" data-name="{{name}}">
           {{#each matchedRecordsList}}
              <div><a href="{{link}}">{{label}}</a></div>
           {{/each}}
           </div>
        {{/each}}{{else}}
        <div>...</div>
        {{/if}}
    </div>
</div>