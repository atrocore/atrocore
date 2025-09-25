{{#if isEmpty}}
<span class="text-gray">{{{translate 'Null'}}}</span>
{{else}}
<div class="options">
    <div class="options-list-container list-group">
     <div class="list-group-item">
         <div style="margin: 5px 0; display:flex; gap: 15px; ">
                <div class="cell form-group" style="flex: 1">
                    <label class="control-label" >
                        <span class="label-text">{{translate 'color'}}</span>
                    </label>
               </div>
              <div class="cell form-group"  style="flex: 1">
                    <label class="control-label" >
                        <span class="label-text">{{translate 'code'}}</span>
                    </label>
                </div>
                <div class="cell  form-group" style="flex: 1">
                    <label class="control-label" >
                        <span class="label-text">{{translate 'label'}}</span>
                    </label>
                </div>
        </div>
    </div>
        {{#each itemDataList}}
        <div class="list-group-item" style="position: relative; padding-right: 25px" data-index="{{@index}}">
             <div class="" style="margin: 5px 0; display:flex; gap: 15px">
               <div class="options-container" style="flex: 1" >
                   <div class="field-container"  data-key="{{colorViewKey}}">
                        {{{var colorViewKey ../this}}}
                    </div>
                </div>
                <div class="options-container" style="flex: 1" >
                   <div class="field-container" data-key="{{codeViewKey}}">
                    {{{var codeViewKey ../this}}}
                    </div>
                </div>
                <div class="options-container " style="flex: 1"  >
                    <div class="field-container" data-key="{{labelViewKey}}">
                          {{{var labelViewKey ../this}}}
                    </div>
                </div>
            </div>
        </div>
        {{/each}}
    </div>
</div>
{{/if}}