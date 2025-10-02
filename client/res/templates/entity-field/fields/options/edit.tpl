<div class="options">
    <div class="options-list-container list-group" style="margin-bottom: 10px">
        <div class="list-group-item">
            <div style="margin: 5px 0; display:flex; gap: 15px">
                <div data-name="draggableIcon">
                    <div style="height: 18px; width: 18px"></div>
                </div>
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
       <div class="list-container">
           {{#each itemDataList}}
           <div class="list-group-item" data-index="{{index}}">
               <div class="clearfix option-list-item-header">
                   <div class="pull-right inline-actions">
                       <a href="javascript:" data-action="removeOptionList" data-index="{{index}}" class="remove-option-list" title="{{translate 'Remove'}}">
                           <i class="ph ph-minus"></i>
                       </a>
                   </div>
               </div>
               <div class="list-group-item-fields stream-head-container" style="margin: 5px 0; display:flex; gap: 15px" data-index="{{index}}">
                   <div data-name="draggableIcon">
                       <i class="ph ph-arrows-out-cardinal"></i>
                   </div>
                   <div class="field" style="flex: 1">
                       <div class="field-container"  data-key="{{colorViewKey}}">
                           {{{var colorViewKey ../this}}}
                       </div>
                   </div>
                   <div class="field" style="flex: 1">
                       <div class="field-container"  data-key="{{codeViewKey}}">
                           {{{var codeViewKey ../this}}}
                       </div>
                   </div>
                   <div class="field" style="flex: 1">
                       <div class="field-container"  data-key="{{labelViewKey}}">
                           {{{var labelViewKey ../this}}}
                       </div>
                   </div>
               </div>
           </div>
           {{/each}}
       </div>
    </div>
    <div class="inline-actions">
        <a href="javascript:" data-action="addOptionList" title="{{translate 'Add'}}" class="add-option-list"><span class="ph ph-plus"></span></a>
    </div>
</div>