<div class="conditional-disable-options">
    <div class="conditional-disable-options-list-container list-group">
        {{#each itemDataList}}
        <div class="list-group-item">
            <div class="clearfix option-list-item-header">
                <div class="pull-right">
                    <a href="javascript:" data-action="removeOptionList" data-index="{{index}}" class="remove-option-list" title="{{translate 'Remove'}}">
                        <i class="ph ph-minus"></i>
                    </a>
                </div>
            </div>
            <div>
                <div class="options-container" data-key="{{optionsViewKey}}">
                    {{{var optionsViewKey ../this}}}
                </div>
            </div>
            <div>
                <div class="pull-right">
                    <a href="javascript:" data-action="editConditions" data-index="{{index}}">{{translate 'Edit'}}</a>
                </div>
                <div class="string-container" data-key="{{conditionGroupViewKey}}">
                    {{{var conditionGroupViewKey ../this}}}
                </div>
            </div>
        </div>
        {{/each}}
    </div>
    <div>
        <a href="javascript:" data-action="addOptionList" title="{{translate 'Add'}}" class="add-option-list"><span class="ph ph-plus"></span></a>
    </div>
</div>