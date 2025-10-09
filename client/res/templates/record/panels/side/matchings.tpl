<div class="row">
    <div class="cell col-sm-12 form-group">
        {{#if matchesList}}{{#each matchesList}}
           <div class="form-group">
              <label class="control-label"><span class="label-text" data-name="{{name}}">{{label}}</span></label>
              <div class="list-container" data-name="{{name}}"></div>
            </div>  
        {{/each}}
        {{/if}}
    </div>
</div>