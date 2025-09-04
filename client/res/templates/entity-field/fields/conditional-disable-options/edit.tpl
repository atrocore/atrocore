<div class="conditional-disable-options">
    <div class="conditional-disable-options-list-container list-group" style="margin-bottom: 10px">
        {{#each itemDataList}}
        <div class="list-group-item">
            <div class="clearfix option-list-item-header">
                <div class="pull-right inline-actions">
                    <a href="javascript:" data-action="removeOptionList" data-index="{{index}}" class="remove-option-list" title="{{translate 'Remove'}}">
                        <i class="ph ph-minus"></i>
                    </a>
                </div>
            </div>
            <div style="margin: 5px 0">
                <div class="options-container" data-key="{{optionsViewKey}}">
                    {{{var optionsViewKey ../this}}}
                </div>
            </div>
            <div>
                <div class="pull-right inline-actions">
                    <a href="javascript:" data-action="editConditions" data-index="{{index}}" title="{{translate 'Configure'}}"><i class="ph ph-gear-six"></i></a>
                </div>
                <div class="string-container" data-key="{{conditionGroupViewKey}}">
                    {{{var conditionGroupViewKey ../this}}}
                </div>
            </div>
        </div>
        {{/each}}
    </div>
    <div class="inline-actions">
        <a href="javascript:" data-action="addOptionList" title="{{translate 'Add'}}" class="add-option-list"><span class="ph ph-plus"></span></a>
    </div>
</div>