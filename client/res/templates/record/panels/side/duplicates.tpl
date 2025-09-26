<div class="row">
    <div class="cell col-sm-12 form-group" data-name="{{name}}">
        <label class="control-label" data-name="{{name}}"><span class="label-text">{{label}}</span></label>
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