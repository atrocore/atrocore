<div class="row">
    <div class="cell col-sm-12 form-group">
        <label class="control-label"><span class="label-text" data-action="findDuplicates" style="cursor: pointer" title="{{translate 'findDuplicates'}}">{{translate 'Duplicates'}}</span></label>
        <div>
        {{#if duplicatesList}}
          {{#each duplicatesList}}
          <a href="{{link}}">{{label}}</a>
          {{/each}}
        {{else}}
          ...
        {{/if}}
        </div>
    </div>
</div>