<div class="row">
    <div class="cell col-sm-12 form-group">
        <label class="control-label"><span class="label-text" data-action="findMatches" style="cursor: pointer" title="{{translate 'findMatches'}}">{{translate 'Duplicates'}}</span></label>
        <div>
        {{#if matchesList}}
          {{#each matchesList}}
          <a href="{{link}}">{{label}}</a>
          {{/each}}
        {{else}}
          ...
        {{/if}}
        </div>
    </div>
</div>