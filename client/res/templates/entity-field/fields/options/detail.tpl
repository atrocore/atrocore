{{#if isEmpty}}
<span class="text-gray">{{{translate 'Null'}}}</span>
{{else}}
<div class="options">
    <div class="options-list-container list-group">
     <div class="list-group-item">
         <div style="margin: 5px 0; display:flex; gap: 15px">
            <div class="cell form-group"  style="flex: 1">
                <label class="control-label" >
                    <span class="label-text">Code</span>
                </label>
            </div>
            <div class="cell  form-group" style="flex: 1">
                <label class="control-label" >
                    <span class="label-text">Label</span>
                </label>
            </div>
            <div class="cell form-group" style="flex: 1">
                <label class="control-label" >
                    <span class="label-text">Color</span>
                </label>

            </div>
        </div>
    </div>
        {{#each itemDataList}}
        <div class="list-group-item">
             <div class="stream-head-container" style="margin: 5px 0; display:flex; gap: 15px">
                <div class="options-container" style="flex: 1" data-key="{{codeViewKey}}">
                    {{{var codeViewKey ../this}}}
                </div>
                <div class="options-container stream-head-container" style="flex: 1"  data-key="{{labelViewKey}}">
                    {{{var labelViewKey ../this}}}
                </div>
                <div class="options-container stream-head-container" style="flex: 1"  data-key="{{colorViewKey}}">
                    {{{var colorViewKey ../this}}}
                </div>
            </div>
        </div>
        {{/each}}
    </div>
</div>
{{/if}}